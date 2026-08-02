<?php

namespace App\Http\Controllers\PublicPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicPortal\PublicTimelineRequest;
use App\Models\Agency;
use App\Models\District;
use App\Services\PublicPortal\PublicDashboardService;
use App\Services\PublicPortal\PublicNavigationService;
use App\Services\PublicPortal\PublicRecentlyIndexedService;
use Illuminate\View\View;

class PublicHomeController extends Controller
{
    public function __invoke(
        PublicTimelineRequest $request,
        PublicDashboardService $dashboard,
        PublicNavigationService $navigation,
        PublicRecentlyIndexedService $recentlyIndexed,
    ): View {
        $filters = $request->filters();
        $dhaka = District::query()->whereRaw('LOWER(name) = ?', ['dhaka'])->first();

        return view('public.home', [
            'summary' => $dashboard->summary(),
            'navigation' => $navigation->links(),
            'earthJourneyEnabled' => (bool) config('civiclens.earth_journey.enabled'),
            'recentlyIndexed' => $recentlyIndexed->timeline($filters),
            'timelineFilters' => $filters,
            'districts' => District::query()->orderBy('name')->get(['id', 'name']),
            'publishers' => Agency::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'dhakaDistrict' => $dhaka,
        ]);
    }
}
