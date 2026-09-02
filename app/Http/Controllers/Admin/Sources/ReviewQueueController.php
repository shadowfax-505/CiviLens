<?php

namespace App\Http\Controllers\Admin\Sources;

use App\Http\Controllers\Controller;
use App\Models\ExtractionField;
use App\Models\ExtractionFieldJudgement;
use App\Models\ExtractionPage;
use App\Models\ExtractionRun;
use App\Models\ExtractionTableCell;
use App\Models\SourceArtifactVersion;
use App\Models\SourcePublisher;
use App\Services\Extraction\AmountGrouping;
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
    public function __construct(
        private readonly ValueLocator $locator,
        private readonly AmountGrouping $grouping,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('viewAny', SourcePublisher::class) === true, 403);

        // Which calibration group to draw from, remembered so the choice
        // survives the redirect after each judgement. `?group=` with no value
        // returns to the whole corpus.
        //
        // Without this a rare publisher is unreachable in practice rather than
        // in principle. cbad-bangladesh holds 69 of 17,773 unlabelled
        // candidates, so drawing uniformly over the corpus means about 5,000
        // judgements to find the 19 its own calibration needs. The
        // group-conditional scheme of ADR-018 exists for publishers exactly
        // that rare, and the queue was the reason it could never be used on one.
        if ($request->has('group')) {
            $chosen = trim((string) $request->query('group'));
            $request->session()->put('review.group', $chosen === '' ? null : $chosen);
        }

        $group = $request->session()->get('review.group');
        $group = is_string($group) && $group !== '' ? $group : null;

        [$publisher, $script] = $group === null ? [null, null] : $this->splitGroup($group);

        // A share of the queue is items somebody else has already judged, shown
        // to a different person so agreement can be measured. Without it the
        // double-judged subset never comes into existence: the queue serves
        // unjudged values, so a second reviewer would spend a whole session
        // never once seeing what the first had seen.
        //
        // The item is drawn at random from that pool exactly as any other is.
        // Choosing which already-judged values to re-show — the hard ones, the
        // disagreements — would measure agreement on a sample selected for
        // disagreement.
        $second = $request->query('second') !== null;

        // Auditing what was already certified, rather than adding to the
        // calibration set.
        //
        // 2,394 published figures were read at a token confidence below 80, and
        // the whole labelled evidence for that stratum is seven values, none
        // wrong — an interval running to 41%. That is not reassurance, it is an
        // absence of measurement, and it sits exactly where the guarantee is
        // least tested.
        //
        // Drawn uniformly *within* the stratum, and the judgements are kept out
        // of calibration and out of the realized-rate estimate: a targeted
        // sample cannot stand in for a uniform one. It measures the error rate
        // inside its own stratum, which is the question being asked.
        $audit = $request->query('audit') !== null;

        $item = ExtractionField::query()
            ->with(['page', 'tableCell'])
            // One copy of each file. The same document reached the archive from
            // two addresses often enough that 75 of 673 artifacts are a second
            // copy of one already held, and a judgement made on a duplicate
            // enters calibration as though it were an independent document.
            // Uniform-at-random is not enough on its own: the draw has to be
            // from distinct documents, or the same page can be sampled twice
            // and the bound is arithmetic about a population that does not
            // exist.
            ->whereIn('extraction_run_id', ExtractionRun::query()
                ->whereIn('source_artifact_version_id', SourceArtifactVersion::query()->canonical()->select('id'))
                ->select('id'))
            ->when($second, fn ($query) => $query
                ->whereNotNull('gold_source')
                ->where('reviewed_by', '!=', $request->user()->getKey())
                ->whereNotIn('id', ExtractionFieldJudgement::query()
                    ->where('user_id', $request->user()->getKey())
                    ->select('extraction_field_id')))
            // Uniform at random, never by confidence. Queueing the most
            // confident items first would make the calibration set
            // unrepresentative of what the system accepts, and a bound computed
            // from it would be correct arithmetic about the wrong population.
            ->when(! $second && ! $audit, fn ($query) => $query->whereNull('is_correct')->whereNull('gold_source'))
            ->when($audit, fn ($query) => $query
                ->where('decision', 'accepted')
                ->whereNotNull('token_confidence')
                ->where('token_confidence', '<', (float) config('civiclens.extraction.ocr.accept_token_confidence', 80.0))
                ->whereNull('is_correct')
                ->whereNull('gold_source'))
            // Narrowing to a group does not weaken that. Exchangeability is
            // required within the group a threshold is fitted on, and
            // calibration is already per publisher and script, so drawing
            // uniformly from one group is exactly as valid as drawing from the
            // corpus and discarding everything else — it merely stops wasting
            // 257 draws in 258. Choosing which items *inside* the group to show
            // would still break it, and nothing here does.
            // Narrowed to the calibration group, which is publisher AND script.
            //
            // Filtering by publisher alone was the reason nineteen labels per
            // group was slow work. A threshold is fitted per publisher x script,
            // so judging "cbad-bangladesh" draws across its bn, en and mixed
            // pools at once — and cbad|mixed holds 40 candidates out of 5,986,
            // so the group that needs 19 labels would almost never be shown.
            //
            // Exchangeability is required within the group a threshold is
            // fitted on, and this is exactly that group, so drawing uniformly
            // inside it is precisely as valid as drawing from the corpus and
            // discarding everything else. It merely stops discarding 99 draws
            // in 100. Choosing which items *inside* the group to show would
            // break it, and nothing here does.
            ->when($publisher !== null, fn ($query) => $query->where('publisher_group', $publisher))
            // A publisher-only value can still be in a session from before this
            // was per-script, and it should keep working.
            ->when($script !== null, fn ($query) => $query->where('script_class', $script))
            ->inRandomOrder()
            ->first();

        // Computed once and shared: the chips and the progress table are the
        // same list, and two queries building the same group keys is how they
        // drift apart.
        $progress = $this->progress();

        return view('admin.sources.review', [
            'item' => $item,
            'progress' => $progress,
            'row' => $this->row($item),
            'group' => $group,
            'groups' => $progress['levels'],
            'second_opinion' => $second,
            'audit' => $audit,
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
            // A comma where the page may show a decimal point. Told to the
            // reviewer as something to check, never corrected: rewriting it
            // would be the system answering the question it is asking.
            'separator_suspect' => $item instanceof ExtractionField
                && $item->field_key === 'amount'
                && ! $this->grouping->groupsCorrectly((string) $item->extracted_value),
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

        // Recorded before the field is touched, and recorded for every reviewer
        // rather than only the first. The field keeps one verdict because the
        // calibration needs one; agreement between people needs all of them,
        // and it is the measurement that says whether the labels the bound
        // rests on are reproducible at all.
        ExtractionFieldJudgement::query()->updateOrCreate(
            [
                'extraction_field_id' => $field->getKey(),
                'user_id' => $request->user()->getKey(),
            ],
            [
                'verdict' => $data['verdict'],
                'note' => $data['note'] ?? null,
                // Re-extraction can change a value under a judgement, and a
                // label about characters that are no longer there is worse than
                // no label.
                'value_judged' => (string) $field->extracted_value,
            ],
        );

        // A field already judged by someone else keeps that judgement. The
        // second opinion is evidence about the first, not a correction of it.
        if ($field->gold_source !== null && (int) $field->reviewed_by !== (int) $request->user()->getKey()) {
            return back()->with('status', 'Recorded as a second opinion.');
        }

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
            // An audit judgement is not a draw from the population, so it
            // belongs to neither the calibration set nor the held-out sample
            // that estimates the realized rate. Both filter on those two names
            // explicitly, so a third keeps it out of the bound entirely.
            'calibration_split' => $request->boolean('audit') ? 'audit' : $this->split(),
            'reviewed_by' => $request->user()->getKey(),
            'reviewed_at' => now(),
        ])->save();

        return back()->with('status', $correct ? 'Marked correct.' : 'Marked incorrect.');
    }

    /**
     * A group key as publisher and script.
     *
     * @return array{0: string, 1: string|null}
     */
    private function splitGroup(string $group): array
    {
        if (! str_contains($group, '|')) {
            return [$group, null];
        }

        [$publisher, $script] = explode('|', $group, 2);

        return [$publisher, $script === '' ? null : $script];
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
     * @return array{adjudicated: int, remaining: int, groups: array<string, int>, minimum: int, levels: list<array{key: string, size: int, alpha: float, at_target: bool, waiting: int, to_target: int, best_possible_alpha: float, remaining_effort: int, judged: int, held_out: int, unscorable: int, uncovered_kinds: array<string, int>}>, ceiling: float}
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

        // Groups holding candidates and no labels at all.
        //
        // Without these the table listed only groups that already certify
        // something, and titled itself "what each group can certify as it
        // stands" — so the groups certifying nothing, which are exactly the
        // ones where nineteen judgements unlock thousands of values, were the
        // ones a reviewer could not see. A summary that omits every zero
        // describes the work already done rather than the work available.
        // An aggregate over rows, so the base builder rather than the model:
        // a count is not an ExtractionField and pretending otherwise invents a
        // property on the model that does not exist.
        // What a reviewer actually did, beside what the calibration could use.
        //
        // Two things quietly discount a judgement and neither was visible on
        // this screen. A share of every judgement is held out to measure error
        // rather than to fit the threshold, and a value whose score is null —
        // deferred by rule as unlocatable, or for a disputed sign, alphabet or
        // leading digit — cannot be in a conformal calibration set at all.
        //
        // So a reviewer judged 624 values in one group and saw 19 counted, with
        // nothing on the page to explain the other 605. That is a screen
        // moving the goalposts, and it was mine.
        $judged = ExtractionField::query()
            ->whereNotNull('is_correct')
            ->selectRaw('publisher_group, script_class, count(*) as aggregate')
            ->groupBy('publisher_group', 'script_class')
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                (string) $row->publisher_group.'|'.(string) $row->script_class => (int) $row->aggregate,
            ])
            ->all();

        $unscorable = ExtractionField::query()
            ->whereNotNull('is_correct')
            ->whereNull('nonconformity_score')
            ->selectRaw('publisher_group, script_class, count(*) as aggregate')
            ->groupBy('publisher_group', 'script_class')
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                (string) $row->publisher_group.'|'.(string) $row->script_class => (int) $row->aggregate,
            ])
            ->all();

        // Which kinds of value each group has labelled, and which it has not.
        //
        // Reported, never used to choose what to show. A threshold is fitted per
        // publisher and script, so the draw has to be uniform inside that group;
        // filtering the queue by kind would enrich the calibration set in the
        // kind being filtered for and break the exchangeability the group filter
        // above is careful to preserve. It would buy the court kinds a
        // certification by invalidating what the certification means.
        //
        // So this tells a reviewer what is withheld and why, and the remedy —
        // calibrating per kind, not merely refusing kinds — is a decision
        // recorded in ADR-018 rather than something this screen can do.
        $labelledKinds = ExtractionField::query()
            ->calibratable()
            ->where('calibration_split', 'calibration')
            ->get(['publisher_group', 'script_class', 'field_key'])
            ->countBy(fn (ExtractionField $field): string => $field->publisher_group.'|'.$field->script_class.'|'.$field->field_key)
            ->all();

        $candidateKinds = ExtractionField::query()
            ->whereNull('gold_source')
            ->whereNull('is_correct')
            ->toBase()
            ->selectRaw('publisher_group, script_class, field_key, count(*) as aggregate')
            ->groupBy('publisher_group', 'script_class', 'field_key')
            ->get();

        $uncovered = [];

        foreach ($candidateKinds as $row) {
            $key = (string) $row->publisher_group.'|'.(string) $row->script_class;

            if (array_key_exists($key.'|'.(string) $row->field_key, $labelledKinds)) {
                continue;
            }

            $uncovered[$key][(string) $row->field_key] = (int) $row->aggregate;
        }

        foreach ($uncovered as $key => $kinds) {
            arsort($kinds);
            $uncovered[$key] = $kinds;
        }

        $unlabelled = ExtractionField::query()
            ->whereNull('gold_source')
            ->whereNull('is_correct')
            ->toBase()
            ->selectRaw('publisher_group, script_class, count(*) as aggregate')
            ->groupBy('publisher_group', 'script_class')
            ->get();

        foreach ($unlabelled as $row) {
            $key = (string) $row->publisher_group.'|'.(string) $row->script_class;

            if (! array_key_exists($key, $groups)) {
                $groups[$key] = 0;
            }
        }

        // Groups where every judgement is unscorable and nothing is left to
        // judge appear in neither list, so they appeared nowhere at all.
        //
        // parliament-bangladesh|bn is one: fifteen values judged, none of them
        // scorable, no candidates remaining. A reviewer had no way to learn the
        // group existed, let alone that it is stuck — and a screen that omits a
        // group certifying nothing is the same silent narrowing as a report
        // that omits a zero.
        foreach (array_keys($judged) as $key) {
            if (! array_key_exists($key, $groups)) {
                $groups[$key] = 0;
            }
        }

        $waiting = $unlabelled
            ->mapWithKeys(fn (object $row): array => [
                (string) $row->publisher_group.'|'.(string) $row->script_class => (int) $row->aggregate,
            ])
            ->all();

        $alpha = (float) config('civiclens.extraction.default_alpha', 0.05);

        $ceiling = (float) config('civiclens.extraction.conformal.alpha_ceiling', 0.25);
        $levels = [];

        // What each group can support as it stands, rather than one number every
        // group is measured against. A rare publisher will never reach nineteen,
        // and telling a reviewer it is certified for nothing describes the target
        // rather than the group.
        foreach ($groups as $key => $size) {
            $attainable = 1.0 / ($size + 1);
            $levels[] = [
                'key' => (string) $key,
                'size' => (int) $size,
                'alpha' => round(max($alpha, $attainable), 3),
                'at_target' => $attainable <= $alpha,
                'waiting' => $waiting[$key] ?? 0,
                // How many more labels this group needs to reach the target
                // level. The finish line, rather than the size of the pile:
                // a chip reading "3,353 waiting" tells a reviewer the work is
                // endless when they are sixteen judgements from certifying it.
                'to_target' => max(0, (int) ceil(1 / $alpha) - 1 - (int) $size),
                // And the level it could reach if every value it holds were
                // judged. One group holds sixteen candidates against a target
                // that needs nineteen, so "19 more" is a finish line that does
                // not exist — the honest number is the level the group can
                // actually support, which is looser and not nothing.
                'best_possible_alpha' => round(max($alpha, 1.0 / ((int) $size + ($waiting[$key] ?? 0) + 1)), 3),
                // What finishing this group actually costs: the labels it still
                // needs, or every candidate it has if it holds fewer than that.
                // Sorting on this puts the cheapest completions first, which is
                // the difference between a reviewer finishing four groups in an
                // hour and finishing none.
                'judged' => $judged[$key] ?? 0,
                // Kinds of value this group holds candidates for and has never
                // labelled. Those cannot certify at all, whatever the group's
                // label count reaches, so a group can read "at target" and still
                // withhold thousands of values.
                'uncovered_kinds' => $uncovered[(string) $key] ?? [],
                'held_out' => max(0, ($judged[$key] ?? 0) - (int) $size - ($unscorable[$key] ?? 0)),
                // Judged, and unusable for fitting a threshold until the value
                // can be scored. Almost all of these are waiting on a re-score
                // rather than on more judgements.
                'unscorable' => $unscorable[$key] ?? 0,
                'remaining_effort' => min(
                    max(0, (int) ceil(1 / $alpha) - 1 - (int) $size),
                    $waiting[$key] ?? 0,
                ),
            ];
        }

        // Cheapest unfinished group first; finished and empty groups last.
        // A reviewer opening this screen should see the group they can complete
        // soonest at the top, not the one that happens to be largest.
        usort($levels, function (array $a, array $b): int {
            $spent = fn (array $level): bool => $level['remaining_effort'] === 0;

            return [$spent($a), $a['remaining_effort'], -$a['size']]
                <=> [$spent($b), $b['remaining_effort'], -$b['size']];
        });

        return [
            'adjudicated' => ExtractionField::query()->where('gold_source', 'reviewer')->count(),
            'remaining' => ExtractionField::query()->whereNull('gold_source')->count(),
            'groups' => $groups,
            // The finite-sample bound needs this many in a group before that
            // group can be certified at the target alpha specifically.
            'minimum' => (int) ceil(1 / $alpha) - 1,
            'levels' => $levels,
            'ceiling' => $ceiling,
        ];
    }
}
