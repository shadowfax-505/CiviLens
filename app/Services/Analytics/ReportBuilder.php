<?php

namespace App\Services\Analytics;

use App\Events\ReportGenerated;
use App\Models\AnalyticsReport;
use App\Models\User;
use App\Support\Analytics\AnalyticsFilters;
use Illuminate\Support\Str;

class ReportBuilder
{
    public function __construct(private readonly DashboardService $dashboards) {}

    public function generate(string $dashboard, string $format, AnalyticsFilters $filters, ?User $user = null): AnalyticsReport
    {
        $payload = $this->dashboards->dashboard($dashboard, $filters, $user);

        $report = AnalyticsReport::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'name' => $payload['title'].' Report',
            'dashboard' => $dashboard,
            'format' => $format,
            'status' => 'generated',
            'filters' => $filters->toArray(),
            'payload' => $payload,
            'generated_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);

        ReportGenerated::dispatch($report);

        return $report;
    }

    public function csv(AnalyticsReport $report): string
    {
        $rows = ['Metric,Value,Unit'];
        $metrics = data_get($report->getAttribute('payload'), 'metrics', []);

        if (! is_array($metrics)) {
            return implode("\n", $rows)."\n";
        }

        foreach ($metrics as $metric) {
            if (! is_array($metric)) {
                continue;
            }

            $rows[] = sprintf('"%s","%s","%s"', $metric['label'], $metric['value'], $metric['unit'] ?? '');
        }

        return implode("\n", $rows)."\n";
    }
}
