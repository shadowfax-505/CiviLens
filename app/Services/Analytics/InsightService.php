<?php

namespace App\Services\Analytics;

use App\Events\AlertTriggered;
use App\Events\InsightGenerated;
use App\Models\AnalyticsAlert;
use App\Models\AnalyticsAlertRule;
use App\Models\User;

class InsightService
{
    /**
     * @param  array<string, mixed>  $dashboard
     * @return array<int, array<string, mixed>>
     */
    public function evaluate(array $dashboard, ?User $user = null): array
    {
        $this->ensureDefaultRules();
        $alerts = [];

        foreach (AnalyticsAlertRule::query()->where('is_active', true)->orderBy('sort_order')->get() as $rule) {
            $metric = $dashboard['metrics'][$rule->metric_key] ?? null;

            if (! is_array($metric)) {
                continue;
            }

            $value = (float) $metric['value'];

            if (! $this->passes($value, $rule->operator, (float) $rule->threshold)) {
                continue;
            }

            $alert = AnalyticsAlert::query()->create([
                'analytics_alert_rule_id' => $rule->id,
                'title' => $rule->name,
                'message' => str_replace([':metric', ':value', ':threshold'], [$metric['label'], (string) $value, (string) $rule->threshold], $rule->message_template),
                'severity' => $rule->severity,
                'status' => 'open',
                'triggered_value' => $value,
                'context' => ['metric' => $metric, 'dashboard' => $dashboard['dashboard'] ?? 'executive', 'user_id' => $user?->id],
                'triggered_at' => now(),
            ]);

            $alerts[] = $alert->toArray();
            AlertTriggered::dispatch($alert);
        }

        InsightGenerated::dispatch($dashboard['dashboard'] ?? 'executive', $alerts);

        return $alerts;
    }

    private function passes(float $value, string $operator, float $threshold): bool
    {
        return match ($operator) {
            '>' => $value > $threshold,
            '>=' => $value >= $threshold,
            '<' => $value < $threshold,
            '<=' => $value <= $threshold,
            '=' => $value === $threshold,
            default => false,
        };
    }

    private function ensureDefaultRules(): void
    {
        AnalyticsAlertRule::query()->firstOrCreate(
            ['slug' => 'budget-utilization-warning'],
            [
                'name' => 'Budget utilization warning',
                'category' => 'finance',
                'metric_key' => 'finance.budget_utilization',
                'operator' => '>=',
                'threshold' => 90,
                'severity' => 'warning',
                'message_template' => ':metric reached :value%, above the :threshold% threshold.',
                'is_active' => true,
                'sort_order' => 10,
            ],
        );

        AnalyticsAlertRule::query()->firstOrCreate(
            ['slug' => 'delayed-projects-watch'],
            [
                'name' => 'Delayed projects watch',
                'category' => 'projects',
                'metric_key' => 'projects.delayed',
                'operator' => '>=',
                'threshold' => 1,
                'severity' => 'warning',
                'message_template' => ':metric reached :value, above the :threshold threshold.',
                'is_active' => true,
                'sort_order' => 20,
            ],
        );
    }
}
