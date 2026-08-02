<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsReport;
use App\Models\CitizenReport;
use App\Models\Document;
use App\Models\Project;
use App\Models\Tender;
use App\Models\User;
use App\Services\Executive\ExecutiveDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExecutiveDashboardController extends Controller
{
    public function __invoke(Request $request, ExecutiveDashboardService $dashboard): View
    {
        $user = $request->user();

        if ($user instanceof User && $user->can('viewDashboard', CitizenReport::class)) {
            $reports = CitizenReport::query()
                ->with(['category', 'status'])
                ->where('submitter_id', $user->id)
                ->latest('submitted_at')
                ->limit(5)
                ->get();

            return view('citizen.dashboard', [
                'reports' => $reports,
                'reportStatusCounts' => CitizenReport::query()
                    ->where('submitter_id', $user->id)
                    ->join('citizen_report_statuses', 'citizen_reports.citizen_report_status_id', '=', 'citizen_report_statuses.id')
                    ->selectRaw('citizen_report_statuses.name, count(*) as total')
                    ->groupBy('citizen_report_statuses.id', 'citizen_report_statuses.name')
                    ->pluck('total', 'name'),
                'publicCounts' => [
                    'projects' => Project::query()->where('is_public', true)->where('is_active', true)->count(),
                    'tenders' => Tender::query()->where('is_public', true)->where('is_active', true)->count(),
                    'documents' => Document::query()
                        ->whereHas('visibility', fn ($query) => $query->where('slug', 'public'))
                        ->count(),
                ],
            ]);
        }

        return view('dashboard', [
            'summary' => $user?->can('viewAny', AnalyticsReport::class) === true ? $dashboard->summary() : null,
        ]);
    }
}
