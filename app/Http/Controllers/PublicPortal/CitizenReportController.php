<?php

namespace App\Http\Controllers\PublicPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreCitizenReportRequest;
use App\Models\CitizenReport;
use App\Models\CitizenReportCategory;
use App\Models\User;
use App\Services\PublicPortal\CitizenReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CitizenReportController extends Controller
{
    public function create(): View
    {
        return view('public.reports.create', [
            'categories' => CitizenReportCategory::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(StoreCitizenReportRequest $request, CitizenReportService $reports): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $report = $reports->submit($user, $request->validated());

        return redirect()
            ->route('public.reports.show', $report->public_uuid)
            ->with('status', 'Your report was submitted for review.');
    }

    public function show(string $uuid): View
    {
        return view('public.reports.show', [
            'report' => CitizenReport::query()->with(['category', 'status', 'activities'])->where('public_uuid', $uuid)->firstOrFail(),
        ]);
    }
}
