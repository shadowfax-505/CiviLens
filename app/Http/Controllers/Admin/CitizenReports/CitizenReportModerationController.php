<?php

namespace App\Http\Controllers\Admin\CitizenReports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CitizenReports\UpdateCitizenReportStatusRequest;
use App\Models\CitizenReport;
use App\Models\CitizenReportStatus;
use App\Models\User;
use App\Services\PublicPortal\CitizenReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CitizenReportModerationController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('viewAny', CitizenReport::class) === true, 403);

        return view('admin.citizen-reports.index', [
            'reports' => CitizenReport::query()
                ->with(['category', 'status', 'submitter'])
                ->when($request->query('status'), function ($builder, mixed $status) {
                    $statusSlug = is_array($status) ? '' : $status;

                    return $builder->whereHas('status', fn ($statusQuery) => $statusQuery->where('slug', $statusSlug));
                })
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'statuses' => CitizenReportStatus::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function show(Request $request, CitizenReport $report): View
    {
        abort_unless($request->user()?->can('view', $report) === true, 403);

        return view('admin.citizen-reports.show', [
            'report' => $report->load(['category', 'status', 'submitter', 'activities.actor']),
            'statuses' => CitizenReportStatus::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function status(UpdateCitizenReportStatusRequest $request, CitizenReportService $reports): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $report = $reports->changeStatus($request->report(), $user, $request->validated());

        return redirect()
            ->route('admin.citizen-reports.show', $report)
            ->with('status', 'Citizen report status updated.');
    }

    public function archive(Request $request, CitizenReport $report, CitizenReportService $reports): RedirectResponse
    {
        abort_unless($request->user()?->can('update', $report) === true, 403);
        $reports->archive($report, $request->user());

        return back()->with('status', 'Citizen report archived.');
    }

    public function restore(Request $request, CitizenReport $report, CitizenReportService $reports): RedirectResponse
    {
        abort_unless($request->user()?->can('update', $report) === true, 403);
        $reports->restore($report, $request->user());

        return back()->with('status', 'Citizen report restored.');
    }
}
