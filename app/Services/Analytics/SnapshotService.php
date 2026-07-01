<?php

namespace App\Services\Analytics;

use App\Events\SnapshotGenerated;
use App\Models\AnalyticsSnapshot;
use App\Models\AnalyticsSnapshotPeriod;
use App\Models\User;
use App\Support\Analytics\AnalyticsFilters;

class SnapshotService
{
    public function __construct(private readonly DashboardService $dashboards) {}

    public function generate(string $period, string $dashboard, AnalyticsFilters $filters, ?User $user = null): AnalyticsSnapshot
    {
        $periodModel = AnalyticsSnapshotPeriod::query()->firstOrCreate(
            ['slug' => $period],
            ['name' => str($period)->headline()->toString(), 'sort_order' => $this->sortOrder($period), 'is_active' => true],
        );
        $payload = $this->dashboards->dashboard($dashboard, $filters, $user);

        $snapshot = AnalyticsSnapshot::query()->create([
            'analytics_snapshot_period_id' => $periodModel->id,
            'dashboard' => $dashboard,
            'snapshot_date' => now()->toDateString(),
            'filter_hash' => $filters->hash(),
            'filters' => $filters->toArray(),
            'metrics' => $payload['metrics'],
            'charts' => $payload['charts'],
            'insights' => $payload['forecast'],
            'generated_by' => $user?->id,
            'generated_at' => now(),
        ]);

        SnapshotGenerated::dispatch($snapshot);

        return $snapshot;
    }

    private function sortOrder(string $period): int
    {
        return match ($period) {
            'daily' => 10,
            'weekly' => 20,
            'monthly' => 30,
            'quarterly' => 40,
            'yearly' => 50,
            default => 99,
        };
    }
}
