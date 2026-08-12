<?php

namespace App\Http\Controllers\Admin\Sources;

use App\Http\Controllers\Controller;
use App\Models\SourcePublisher;
use App\Models\TenderObservation;
use App\Services\Intelligence\AmendmentCountReader;
use App\Services\Intelligence\NoticeRevisionDetector;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Show what the crawl has actually observed.
 *
 * The registry page shows which sources are approved and running. Nothing
 * showed what they produced, so the only way to read the findings was to run an
 * artisan command.
 *
 * Everything here counts what a publisher stated about its own notices. Neither
 * indicator infers anything, which is why this page can exist before any
 * calibration does — and why it reports counts rather than conclusions.
 */
class SourceFindingsController extends Controller
{
    public function __invoke(
        Request $request,
        AmendmentCountReader $amendments,
        NoticeRevisionDetector $revisions,
    ): View {
        abort_unless($request->user()?->can('viewAny', SourcePublisher::class) === true, 403);

        $publishers = SourcePublisher::query()
            ->orderBy('name')
            ->get()
            ->map(fn (SourcePublisher $publisher): array => [
                'publisher' => $publisher,
                'amendments' => $amendments->summarise($publisher->getKey()),
                'revisions' => $revisions->detect($publisher->getKey()),
            ])
            // A publisher nothing has been observed from yet would show a page
            // of zeroes that reads as "this publisher changed nothing".
            ->filter(fn (array $row): bool => $row['amendments']['notices'] > 0)
            ->values();

        return view('admin.sources.findings', [
            'rows' => $publishers,
            'recent' => TenderObservation::query()
                ->with('publisher:id,name')
                ->latest('observed_at')
                ->latest('id')
                ->limit(25)
                ->get(),
        ]);
    }
}
