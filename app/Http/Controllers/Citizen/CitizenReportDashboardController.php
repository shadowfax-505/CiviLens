<?php

namespace App\Http\Controllers\Citizen;

use App\Http\Controllers\Controller;
use App\Models\CitizenReport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CitizenReportDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return view('citizen.reports.index', [
            'reports' => CitizenReport::query()
                ->with(['category', 'status'])
                ->where('submitter_id', $user->id)
                ->latest()
                ->paginate(12),
        ]);
    }

    public function show(Request $request, CitizenReport $report): View
    {
        abort_unless($request->user()?->can('view', $report) === true, 403);

        return view('citizen.reports.show', [
            'report' => $report->load([
                'agency',
                'category',
                'country',
                'district',
                'division',
                'document',
                'project',
                'status',
                'union',
                'upazila',
                'ward',
                'activities.actor',
            ]),
        ]);
    }
}
