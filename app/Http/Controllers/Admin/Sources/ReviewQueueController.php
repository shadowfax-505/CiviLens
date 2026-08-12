<?php

namespace App\Http\Controllers\Admin\Sources;

use App\Http\Controllers\Controller;
use App\Models\ExtractionField;
use App\Models\SourcePublisher;
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
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('viewAny', SourcePublisher::class) === true, 403);

        return view('admin.sources.review', [
            // Uniform at random, never by confidence. Queueing the most
            // confident items first would make the calibration set
            // unrepresentative of what the system accepts, and a bound computed
            // from it would be correct arithmetic about the wrong population.
            'item' => ExtractionField::query()
                ->with('page')
                ->whereNull('is_correct')
                ->whereNull('gold_source')
                ->inRandomOrder()
                ->first(),
            'progress' => $this->progress(),
        ]);
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
