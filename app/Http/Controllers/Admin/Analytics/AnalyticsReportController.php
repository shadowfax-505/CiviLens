<?php

namespace App\Http\Controllers\Admin\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Analytics\AnalyticsFilterRequest;
use App\Jobs\GenerateAnalyticsReport;
use App\Models\AnalyticsReport;
use App\Services\Analytics\DashboardService;
use App\Services\Analytics\ReportBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class AnalyticsReportController extends Controller
{
    public function index(AnalyticsFilterRequest $request, DashboardService $dashboards): View
    {
        abort_unless($request->user()?->can('viewAny', AnalyticsReport::class) === true, 403);

        return view('admin.analytics.reports', [
            'reports' => AnalyticsReport::query()->latest()->paginate(15)->withQueryString(),
            'dashboards' => $dashboards->dashboards(),
            'selectedDashboard' => $request->validated('dashboard') ?: 'executive',
            'filters' => $request->filters(),
        ]);
    }

    public function store(AnalyticsFilterRequest $request): RedirectResponse
    {
        abort_unless($request->user()?->can('create', AnalyticsReport::class) === true, 403);

        $dashboard = $request->validated('dashboard') ?: 'executive';
        $format = $request->validated('format') ?: 'csv';

        GenerateAnalyticsReport::dispatch($dashboard, $format, $request->filters()->toArray(), $request->user()->id);

        return back()->with('status', 'analytics-report-queued');
    }

    public function download(AnalyticsReport $report, ReportBuilder $reports): Response
    {
        abort_unless(request()->user()?->can('view', $report) === true, 403);

        return response($reports->content($report), 200, [
            'Content-Type' => $reports->mimeType($report),
            'Content-Disposition' => 'attachment; filename="'.$report->uuid.'.'.$reports->extension($report).'"',
        ]);
    }

    public function csv(AnalyticsFilterRequest $request, ReportBuilder $reports): Response
    {
        abort_unless($request->user()?->can('create', AnalyticsReport::class) === true, 403);

        $dashboard = $request->validated('dashboard') ?: 'executive';
        $report = $reports->generate($dashboard, 'csv', $request->filters(), $request->user());

        return response($reports->content($report), 200, [
            'Content-Type' => $reports->mimeType($report),
            'Content-Disposition' => 'attachment; filename="'.$report->uuid.'.'.$reports->extension($report).'"',
        ]);
    }
}
