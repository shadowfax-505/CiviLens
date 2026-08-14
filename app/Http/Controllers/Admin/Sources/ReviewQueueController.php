<?php

namespace App\Http\Controllers\Admin\Sources;

use App\Http\Controllers\Controller;
use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Models\ExtractionTableCell;
use App\Models\SourcePublisher;
use App\Services\Extraction\ValueLocator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The adjudication queue the conformal bound is computed from.
 *
 * A reviewer's judgement is the only admissible label here. Corruption labels do
 * not exist, and audit findings are not a substitute because audited entities are
 * selected rather than random, which breaks the exchangeability the bound assumes
 * (ADR-016).
 */
class ReviewQueueController extends Controller
{
    public function __construct(private readonly ValueLocator $locator) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('viewAny', SourcePublisher::class) === true, 403);

        $item = ExtractionField::query()
            ->with(['page', 'tableCell'])
            // Uniform at random, never by confidence. Queueing the most
            // confident items first would make the calibration set
            // unrepresentative of what the system accepts, and a bound computed
            // from it would be correct arithmetic about the wrong population.
            ->whereNull('is_correct')
            ->whereNull('gold_source')
            ->inRandomOrder()
            ->first();

        return view('admin.sources.review', [
            'item' => $item,
            'progress' => $this->progress(),
            'row' => $this->row($item),
            // How many places on the page carry these characters. One means the
            // crop is the value; several means the reviewer is judging the
            // reading, not which of them was meant; none means the page was read
            // before word geometry was stored and only the whole page can be
            // shown.
            'located' => $item instanceof ExtractionField && $item->page instanceof ExtractionPage
                ? count($this->locator->locate($item, $item->page))
                : 0,
            // Whether the page shows Bengali digits while the value is written
            // in Latin ones. Left unsaid, two reviewers answer the same item
            // differently and the calibration set stops meaning one thing.
            'transliterated' => $this->transliterated($item),
        ]);
    }

    /**
     * Does the page write these digits in Bengali while the value is in Latin?
     *
     * The audit PDFs carry legacy-font text layers in which Bengali numerals are
     * stored as the Latin bytes that happen to render them, so a page reading
     * ১০০.০০ yields the value 100.00. The figure is right and the characters
     * differ, which is precisely the case a reviewer cannot resolve alone.
     */
    private function transliterated(?ExtractionField $item): bool
    {
        if (! $item instanceof ExtractionField) {
            return false;
        }

        return $item->script_class === 'bn'
            && preg_match('/[0-9]/', (string) $item->extracted_value) === 1
            && preg_match('/[\x{09E6}-\x{09EF}]/u', (string) $item->extracted_value) !== 1;
    }

    /**
     * The table row a value sat in, so a reviewer can see it among its siblings.
     *
     * Empty for a value taken from flat page text. A row is context that makes a
     * figure placeable, and offering an invented one would be worse than
     * offering none.
     *
     * @return array{cells: list<array{column: int, text: string, is_value: bool}>, label: string|null}
     */
    private function row(?ExtractionField $item): array
    {
        $cell = $item?->tableCell;

        if (! $cell instanceof ExtractionTableCell) {
            return ['cells' => [], 'label' => null];
        }

        $cells = ExtractionTableCell::query()
            ->where('extraction_page_id', $cell->extraction_page_id)
            ->where('table_index', $cell->table_index)
            ->where('row_index', $cell->row_index)
            ->orderBy('column_index')
            ->get();

        $columns = [];

        // Built by hand rather than mapped: a Collection carries its keys
        // through ->all(), so the result is a map where the view expects a list.
        foreach ($cells as $current) {
            $columns[] = [
                'column' => (int) $current->column_index,
                'text' => (string) $current->text,
                'is_value' => $current->is($cell),
            ];
        }

        return [
            'cells' => $columns,
            // The leading cell is the line item the row describes. It is shown
            // as read, not as verified: a mis-split row would attach a confident
            // label to the wrong figure, which is worse than no label.
            'label' => $cells->first()?->text,
        ];
    }

    public function store(Request $request, ExtractionField $field): RedirectResponse
    {
        abort_unless($request->user()?->can('viewAny', SourcePublisher::class) === true, 403);

        $data = $request->validate([
            'verdict' => ['required', 'in:correct,incorrect,unsure'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        // Unsure records that the item was seen and left undecided. Forcing a
        // verdict would put a guess into the calibration set, and a coerced
        // label is worse than a missing one: the bound would be computed from
        // it as though someone had actually known.
        if ($data['verdict'] === 'unsure') {
            $field->forceFill([
                'gold_source' => 'reviewer-unsure',
                'reviewed_by' => $request->user()->getKey(),
                'reviewed_at' => now(),
            ])->save();

            return back()->with('status', 'Left undecided.');
        }

        $correct = $data['verdict'] === 'correct';

        $field->forceFill([
            'is_correct' => $correct,
            // The value stands as its own gold when a reviewer confirms it. When
            // they reject it, no gold is invented — the field is wrong, and what
            // it should have said is a separate question this screen does not ask.
            'gold_value' => $correct ? $field->extracted_value : null,
            'gold_source' => 'reviewer',
            'calibration_split' => $this->split(),
            'reviewed_by' => $request->user()->getKey(),
            'reviewed_at' => now(),
        ])->save();

        return back()->with('status', $correct ? 'Marked correct.' : 'Marked incorrect.');
    }

    /**
     * Split assigned at adjudication, not before.
     *
     * Deciding the split when the candidate is generated would let the order
     * items happen to be reviewed in decide which group they land in.
     */
    private function split(): string
    {
        return random_int(1, 100) <= (int) config('civiclens.extraction.calibration_split_percent', 70)
            ? 'calibration'
            : 'test';
    }

    /**
     * @return array{adjudicated: int, remaining: int, groups: array<string, int>, minimum: int}
     */
    private function progress(): array
    {
        $groups = ExtractionField::query()
            ->calibratable()
            ->where('calibration_split', 'calibration')
            ->get(['publisher_group', 'script_class'])
            // Grouped in PHP rather than by a raw aggregate, so the key is built
            // the same way the calibrator builds it and the two cannot drift.
            ->countBy(fn (ExtractionField $field): string => $field->publisher_group.'|'.$field->script_class)
            ->all();

        $alpha = (float) config('civiclens.extraction.default_alpha', 0.05);

        return [
            'adjudicated' => ExtractionField::query()->where('gold_source', 'reviewer')->count(),
            'remaining' => ExtractionField::query()->whereNull('gold_source')->count(),
            'groups' => $groups,
            // The finite-sample bound needs this many in a group before that
            // group can be certified at all.
            'minimum' => (int) ceil(1 / $alpha) - 1,
        ];
    }
}
