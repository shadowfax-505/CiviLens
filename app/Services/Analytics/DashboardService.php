<?php

namespace App\Services\Analytics;

use App\Events\DashboardViewed;
use App\Models\AnalyticsEvent;
use App\Models\User;
use App\Support\Analytics\AnalyticsFilters;

class DashboardService
{
    public function __construct(
        private readonly MetricRegistry $registry,
        private readonly MetricEngine $metrics,
        private readonly ChartService $charts,
        private readonly ForecastPreparationService $forecast,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(string $dashboard, AnalyticsFilters $filters, ?User $user = null): array
    {
        $keys = $this->registry->dashboardMetrics($dashboard);
        $metricResults = $this->metrics->calculateMany($keys, $filters);

        AnalyticsEvent::query()->create([
            'user_id' => $user?->id,
            'event' => 'dashboard.viewed',
            'dashboard' => $dashboard,
            'payload' => ['filters' => $filters->toArray()],
            'occurred_at' => now(),
        ]);

        DashboardViewed::dispatch($dashboard, $user);

        return [
            'dashboard' => $dashboard,
            'title' => $this->title($dashboard),
            'filters' => $filters->toArray(),
            'metrics' => $metricResults,
            'charts' => $this->charts->dashboard($dashboard, $metricResults, $filters),
            'forecast' => $this->forecast->prepare($filters),
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function dashboards(): array
    {
        return [
            'executive' => 'Executive',
            'finance' => 'Finance',
            'procurement' => 'Procurement',
            'contractors' => 'Contractors',
            'agency' => 'Agency',
            'projects' => 'Projects',
            'search' => 'Search',
            'system' => 'System Health',
        ];
    }

    private function title(string $dashboard): string
    {
        return $this->dashboards()[$dashboard] ?? 'Executive';
    }
}
