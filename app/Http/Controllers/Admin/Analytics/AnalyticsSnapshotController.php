<?php

namespace App\Http\Controllers\Admin\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Analytics\AnalyticsFilterRequest;
use App\Jobs\GenerateAnalyticsSnapshot;
use App\Models\AnalyticsSnapshot;
use App\Services\Analytics\SnapshotService;
use Illuminate\Http\RedirectResponse;

class AnalyticsSnapshotController extends Controller
{
    public function store(AnalyticsFilterRequest $request): RedirectResponse
    {
        abort_unless($request->user()?->can('create', AnalyticsSnapshot::class) === true, 403);

        $period = $request->validated('period') ?: 'daily';
        $dashboard = $request->validated('dashboard') ?: 'executive';

        GenerateAnalyticsSnapshot::dispatch($period, $dashboard, $request->filters()->toArray(), $request->user()->id);

        return back()->with('status', 'analytics-snapshot-queued');
    }

    public function realtime(AnalyticsFilterRequest $request, SnapshotService $snapshots): RedirectResponse
    {
        abort_unless($request->user()?->can('create', AnalyticsSnapshot::class) === true, 403);

        $period = $request->validated('period') ?: 'daily';
        $dashboard = $request->validated('dashboard') ?: 'executive';
        $snapshot = $snapshots->generate($period, $dashboard, $request->filters(), $request->user());

        return back()
            ->with('status', 'analytics-snapshot-generated')
            ->with('analytics_snapshot_id', $snapshot->id);
    }
}
