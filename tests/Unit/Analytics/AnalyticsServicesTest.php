<?php

use App\Events\AlertTriggered;
use App\Events\MetricCalculated;
use App\Events\ReportGenerated;
use App\Events\SnapshotGenerated;
use App\Models\AnalyticsAlert;
use App\Models\AnalyticsReport;
use App\Models\AnalyticsSnapshot;
use App\Models\Budget;
use App\Models\ContractorProfile;
use App\Models\Document;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Role;
use App\Models\SearchHistory;
use App\Models\User;
use App\Services\Analytics\DashboardService;
use App\Services\Analytics\InsightService;
use App\Services\Analytics\MetricEngine;
use App\Services\Analytics\MetricRegistry;
use App\Services\Analytics\ReportBuilder;
use App\Services\Analytics\SnapshotService;
use App\Support\Analytics\AnalyticsFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function analyticsAdminUser(): User
{
    $role = Role::query()->create(['name' => 'Administrator', 'slug' => config('civiclens.roles.admin')]);
    $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    $user->roles()->attach($role);

    return $user;
}

it('registers and calculates reusable metrics from source records', function (): void {
    Event::fake([MetricCalculated::class]);

    $inProgress = ProjectStatus::factory()->create(['slug' => 'in-progress', 'name' => 'In Progress']);
    $completed = ProjectStatus::factory()->create(['slug' => 'completed', 'name' => 'Completed', 'is_terminal' => true]);

    $activeProject = Project::factory()->create([
        'project_status_id' => $inProgress->id,
        'planned_end_date' => now()->subDays(5)->toDateString(),
        'actual_end_date' => null,
        'progress_percentage' => 40,
        'is_active' => true,
    ]);
    Project::factory()->create([
        'project_status_id' => $completed->id,
        'actual_end_date' => now()->subDay()->toDateString(),
        'progress_percentage' => 100,
        'is_active' => true,
    ]);
    Budget::factory()->create([
        'project_id' => $activeProject->id,
        'current_allocation' => 1000,
        'actual_expenditure' => 250,
        'reserved_amount' => 100,
        'committed_amount' => 150,
    ]);
    ContractorProfile::factory()->create(['is_blacklisted' => true]);
    Document::factory()->create(['file_size' => 2048, 'ocr_status' => 'pending']);
    SearchHistory::query()->create([
        'user_id' => analyticsAdminUser()->id,
        'query' => 'bridge',
        'module' => 'projects',
        'filters' => [],
        'results_count' => 0,
        'latency_ms' => 42,
        'successful' => false,
    ]);

    $registry = app(MetricRegistry::class);
    $engine = app(MetricEngine::class);
    $filters = AnalyticsFilters::fromArray([]);

    expect($registry->keys())->toContain('projects.active')
        ->and($registry->keys())->toContain('finance.budget_utilization')
        ->and($engine->calculate('projects.active', $filters)->value)->toBe(2)
        ->and($engine->calculate('projects.delayed', $filters)->value)->toBe(1)
        ->and($engine->calculate('finance.budget_utilization', $filters)->value)->toBe(25.0)
        ->and($engine->calculate('contractors.blacklisted', $filters)->value)->toBe(1)
        ->and($engine->calculate('documents.pending_ocr', $filters)->value)->toBe(1)
        ->and($engine->calculate('search.failed_searches', $filters)->value)->toBe(1);

    Event::assertDispatched(MetricCalculated::class);
});

it('builds executive dashboards snapshots reports and rule based alerts', function (): void {
    Event::fake([AlertTriggered::class, ReportGenerated::class, SnapshotGenerated::class]);

    $admin = analyticsAdminUser();
    Budget::factory()->create([
        'current_allocation' => 1000,
        'actual_expenditure' => 925,
        'reserved_amount' => 0,
        'committed_amount' => 0,
    ]);

    $filters = AnalyticsFilters::fromArray(['dashboard' => 'executive']);
    $dashboard = app(DashboardService::class)->dashboard('executive', $filters, $admin);
    $snapshot = app(SnapshotService::class)->generate('daily', 'executive', $filters, $admin);
    $report = app(ReportBuilder::class)->generate('executive', 'csv', $filters, $admin);
    $alerts = app(InsightService::class)->evaluate($dashboard, $admin);

    expect($dashboard['metrics'])->toHaveKey('finance.budget_utilization')
        ->and($dashboard['charts'])->toHaveKey('budget_utilization')
        ->and($snapshot)->toBeInstanceOf(AnalyticsSnapshot::class)
        ->and($report)->toBeInstanceOf(AnalyticsReport::class)
        ->and($alerts)->not->toBeEmpty()
        ->and(AnalyticsAlert::query()->where('severity', 'warning')->exists())->toBeTrue();

    Event::assertDispatched(SnapshotGenerated::class);
    Event::assertDispatched(ReportGenerated::class);
    Event::assertDispatched(AlertTriggered::class);
});
