<?php

namespace App\Http\Controllers\Admin\Intelligence;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Intelligence\IndicatorFilterRequest;
use App\Http\Requests\Admin\Intelligence\ReviewIndicatorRequest;
use App\Models\IntelligenceIndicator;
use App\Models\User;
use App\Services\Intelligence\ExplainabilityService;
use App\Services\Intelligence\IntelligenceDashboardService;
use App\Services\Intelligence\ReviewWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class IntelligenceIndicatorController extends Controller
{
    public function index(IndicatorFilterRequest $request, IntelligenceDashboardService $dashboard): View
    {
        return view('admin.intelligence.indicators.index', [
            'indicators' => $dashboard->indicators($request->filters()),
            'filters' => $request->filters(),
        ]);
    }

    public function show(IntelligenceIndicator $indicator, ExplainabilityService $explainability): View
    {
        abort_unless(request()->user()?->can('view', $indicator) === true, 403);

        return view('admin.intelligence.indicators.show', [
            'indicator' => $indicator,
            'explanation' => $explainability->explain($indicator),
        ]);
    }

    public function review(ReviewIndicatorRequest $request, ReviewWorkflowService $reviews): RedirectResponse
    {
        $indicator = $request->indicator();
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $reviews->review($indicator, $user, $request->validated());

        return redirect()
            ->route('admin.intelligence.indicators.show', $indicator)
            ->with('status', 'Indicator review saved.');
    }
}
