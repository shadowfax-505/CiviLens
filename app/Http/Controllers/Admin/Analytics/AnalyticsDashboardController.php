<?php

namespace App\Http\Controllers\Admin\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Analytics\AnalyticsFilterRequest;
use App\Models\Agency;
use App\Models\AnalyticsAlert;
use App\Models\AnalyticsReport;
use App\Models\District;
use App\Models\Division;
use App\Models\FiscalYear;
use App\Models\FundingSource;
use App\Models\ProcurementMethod;
use App\Models\Project;
use App\Services\Analytics\DashboardService;
use App\Services\Analytics\InsightService;
use Illuminate\View\View;

class AnalyticsDashboardController extends Controller
{
    public function __invoke(AnalyticsFilterRequest $request, DashboardService $dashboards, InsightService $insights): View
    {
        abort_unless($request->user()?->can('viewAny', AnalyticsReport::class) === true, 403);

        $dashboard = $request->validated('dashboard') ?: 'executive';
        $filters = $request->filters();
        $payload = $dashboards->dashboard($dashboard, $filters, $request->user());
        $generatedAlerts = $insights->evaluate($payload, $request->user());

        return view('admin.analytics.dashboard', [
            'payload' => $payload,
            'dashboards' => $dashboards->dashboards(),
            'filters' => $filters,
            'generatedAlerts' => $generatedAlerts,
            'recentAlerts' => AnalyticsAlert::query()->latest('triggered_at')->limit(8)->get(),
            'fiscalYears' => FiscalYear::query()->orderByDesc('starts_on')->get(),
            'agencies' => Agency::query()->orderBy('name')->get(),
            'divisions' => Division::query()->orderBy('name')->get(),
            'districts' => District::query()->orderBy('name')->get(),
            'projects' => Project::query()->orderBy('name')->limit(100)->get(),
            'fundingSources' => FundingSource::query()->orderBy('name')->get(),
            'procurementMethods' => ProcurementMethod::query()->orderBy('name')->get(),
        ]);
    }
}
