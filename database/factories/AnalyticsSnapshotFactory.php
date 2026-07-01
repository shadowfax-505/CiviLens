<?php

namespace Database\Factories;

use App\Models\AnalyticsSnapshot;
use App\Models\AnalyticsSnapshotPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AnalyticsSnapshotFactory extends Factory
{
    protected $model = AnalyticsSnapshot::class;

    public function definition(): array
    {
        $filters = ['dashboard' => 'executive'];

        return [
            'analytics_snapshot_period_id' => AnalyticsSnapshotPeriod::factory(),
            'dashboard' => 'executive',
            'snapshot_date' => now()->toDateString(),
            'filter_hash' => hash('sha256', json_encode($filters, JSON_THROW_ON_ERROR)),
            'filters' => $filters,
            'metrics' => ['projects.active' => ['value' => 1]],
            'charts' => ['budget_utilization' => ['type' => 'doughnut']],
            'insights' => [],
            'generated_by' => User::factory(),
            'generated_at' => now(),
        ];
    }
}
