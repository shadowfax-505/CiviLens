<?php

use App\Models\AnalyticsAlert;
use App\Models\AnalyticsAlertRule;
use App\Models\AnalyticsEvent;
use App\Models\AnalyticsReport;
use App\Models\AnalyticsSnapshot;
use App\Models\AnalyticsSnapshotPeriod;
use App\Models\DashboardState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates normalized analytics platform tables and models', function (): void {
    foreach ([
        'analytics_snapshot_periods',
        'analytics_snapshots',
        'analytics_reports',
        'analytics_alert_rules',
        'analytics_alerts',
        'dashboard_states',
        'analytics_events',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    expect(class_exists(AnalyticsSnapshotPeriod::class))->toBeTrue()
        ->and(class_exists(AnalyticsSnapshot::class))->toBeTrue()
        ->and(class_exists(AnalyticsReport::class))->toBeTrue()
        ->and(class_exists(AnalyticsAlertRule::class))->toBeTrue()
        ->and(class_exists(AnalyticsAlert::class))->toBeTrue()
        ->and(class_exists(DashboardState::class))->toBeTrue()
        ->and(class_exists(AnalyticsEvent::class))->toBeTrue();
});

it('preserves generated snapshots and alerts as immutable analytics history', function (): void {
    $period = AnalyticsSnapshotPeriod::query()->create([
        'name' => 'Daily',
        'slug' => 'daily',
        'sort_order' => 10,
        'is_active' => true,
    ]);

    $snapshot = AnalyticsSnapshot::query()->create([
        'analytics_snapshot_period_id' => $period->id,
        'dashboard' => 'executive',
        'snapshot_date' => '2026-07-01',
        'filters' => ['fiscal_year_id' => 1],
        'metrics' => ['active_projects' => 12],
        'charts' => ['budget_utilization' => ['type' => 'bar']],
        'generated_by' => null,
    ]);

    $rule = AnalyticsAlertRule::query()->create([
        'name' => 'Budget overrun',
        'slug' => 'budget-overrun',
        'category' => 'finance',
        'metric_key' => 'budget_utilization',
        'operator' => '>=',
        'threshold' => 90,
        'severity' => 'warning',
        'message_template' => 'Budget utilization exceeded threshold.',
        'is_active' => true,
    ]);

    $alert = AnalyticsAlert::query()->create([
        'analytics_alert_rule_id' => $rule->id,
        'title' => 'Budget overrun',
        'message' => 'Budget utilization exceeded threshold.',
        'severity' => 'warning',
        'status' => 'open',
        'triggered_value' => 93.5,
        'context' => ['metric' => 'budget_utilization'],
        'triggered_at' => now(),
    ]);

    expect($snapshot->delete())->toBeFalse()
        ->and($alert->delete())->toBeFalse()
        ->and(AnalyticsSnapshot::query()->whereKey($snapshot->id)->exists())->toBeTrue()
        ->and(AnalyticsAlert::query()->whereKey($alert->id)->exists())->toBeTrue();
});
