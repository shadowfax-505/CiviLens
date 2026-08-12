<?php

namespace App\Http\Controllers\Admin\Sources;

use App\Http\Controllers\Controller;
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

        return view('admin.sources.extraction', ['summary' => $report->build()]);
    }
}
