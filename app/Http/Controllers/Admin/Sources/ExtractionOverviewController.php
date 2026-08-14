<?php

namespace App\Http\Controllers\Admin\Sources;

use App\Http\Controllers\Controller;
use App\Models\ExtractionField;
use App\Models\SourcePublisher;
use App\Services\Extraction\ExtractionRoutingReport;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Show what the pipeline managed to read.
 *
 * The figure that matters here is abstention, and it is the one a summary is
 * most tempted to leave out. On this corpus OCR declined to vouch for a large
 * share of the pages it attempted, so a screen reporting only pages extracted
 * would overstate how much text the system actually has.
 */
class ExtractionOverviewController extends Controller
{
    public function __invoke(Request $request, ExtractionRoutingReport $report): View
    {
        abort_unless($request->user()?->can('viewAny', SourcePublisher::class) === true, 403);

        // What the certification actually did. Until this screen existed the
        // thresholds were computed and nothing on any page said whether they had
        // ever been applied to a single value — they had not.
        $decisions = ExtractionField::query()
            ->whereNull('gold_source')
            ->selectRaw('decision, count(*) as total')
            ->groupBy('decision')
            ->pluck('total', 'decision')
            ->all();

        return view('admin.sources.extraction', [
            'summary' => $report->build(),
            'decisions' => [
                'accepted' => (int) ($decisions['accepted'] ?? 0),
                'deferred' => (int) ($decisions['deferred'] ?? 0),
                'pending' => (int) ($decisions['pending'] ?? 0),
                // An acceptance on a threshold borrowed from a wider population
                // is not a conditional claim about this group, and the two must
                // not be counted as one.
                'borrowed' => ExtractionField::query()
                    ->where('decision', 'accepted')
                    ->where('decision_basis', '!=', 'group')
                    ->count(),
            ],
        ]);
    }
}
