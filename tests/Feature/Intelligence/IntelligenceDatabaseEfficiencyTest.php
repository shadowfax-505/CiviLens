<?php

use App\Models\Budget;
use App\Models\IntelligenceIndicator;
use App\Models\IntelligenceRule;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\Intelligence\CivicIntegrityEngineService;
use App\Services\Intelligence\IntelligenceCandidateQueryService;
use App\Services\Intelligence\IntelligenceManager;
use App\Services\Intelligence\RuleManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function intelligenceDatabaseAdmin(): User
{
    $role = Role::query()->firstOrCreate(['slug' => config('civiclens.roles.admin')], ['name' => 'Administrator']);
    $user = User::factory()->create();
    $user->roles()->sync([$role->id]);

    return $user;
}

it('consolidates low-utilization candidates and scores utilization risk deterministically', function (): void {
    $rule = IntelligenceRule::factory()->create([
        'slug' => 'low-budget-utilization',
        'module' => 'finance',
        'thresholds' => ['max_utilization' => 10, 'warning' => 50, 'critical' => 90],
    ]);
    $project = Project::factory()->create();
    $lowUtilizationBudget = Budget::factory()->create([
        'project_id' => $project->id,
        'current_allocation' => 1000,
        'actual_expenditure' => 50,
    ]);
    Budget::factory()->create([
        'project_id' => $project->id,
        'current_allocation' => 1000,
        'actual_expenditure' => 150,
    ]);

    $candidateIds = app(IntelligenceCandidateQueryService::class)->for($rule)->pluck('id')->all();
    $dryRun = app(RuleManagementService::class)->dryRun($rule);
    $indicator = app(IntelligenceManager::class)->runRule($rule)->sole();

    expect($candidateIds)->toBe([$lowUtilizationBudget->id])
        ->and($dryRun['estimated_matches'])->toBe(1)
        ->and($indicator->source_id)->toBe($lowUtilizationBudget->id)
        ->and($indicator->severity)->toBe('critical')
        ->and($indicator->confidence_score)->toBe(95)
        ->and($indicator->detection_payload)->toMatchArray(['utilization_percentage' => 5.0]);
});

it('rejects unordered and out-of-range deterministic rule thresholds', function (): void {
    $admin = intelligenceDatabaseAdmin();
    $rule = IntelligenceRule::factory()->create();

    $this->actingAs($admin)->patch("/admin/intelligence/rules/{$rule->id}", [
        'is_active' => '1',
        'priority' => '25',
        'weight' => '80',
        'severity_default' => 'warning',
        'thresholds' => '{"max_utilization":101,"warning":90,"critical":50}',
        'description' => 'Invalid threshold ordering.',
        'documentation_url' => 'https://example.com/rules/invalid-thresholds',
        'execution_frequency' => 'daily',
    ])->assertSessionHasErrors('thresholds');
});

it('preserves historical indicators while relating new engine indicators to their run', function (): void {
    $historical = IntelligenceIndicator::factory()->create(['civic_intelligence_run_id' => null]);
    $rule = IntelligenceRule::factory()->create([
        'slug' => 'low-budget-utilization',
        'module' => 'finance',
        'thresholds' => ['max_utilization' => 10, 'warning' => 50, 'critical' => 90],
    ]);
    $budget = Budget::factory()->create(['current_allocation' => 1000, 'actual_expenditure' => 50]);

    $run = app(CivicIntegrityEngineService::class)->run();
    $indicator = IntelligenceIndicator::query()->where('intelligence_rule_id', $rule->id)->sole();

    expect($historical->refresh()->civic_intelligence_run_id)->toBeNull()
        ->and($run->indicators)->toHaveCount(1)
        ->and($indicator->run->is($run))->toBeTrue()
        ->and($indicator->source_id)->toBe($budget->id)
        ->and($indicator->metadata)->toMatchArray(['engine_run_id' => $run->id]);
});

it('adds portable indexes for intelligence runs, indicators, and grouped candidates', function (): void {
    expect(Schema::hasColumn('intelligence_indicators', 'civic_intelligence_run_id'))->toBeTrue();

    $indexes = collect([
        'intelligence_indicators',
        'civic_intelligence_runs',
        'awards',
        'citizen_reports',
    ])->flatMap(fn (string $table): array => array_map(
        fn (object $index): string => $table.':'.$index->name,
        DB::select("pragma index_list('{$table}')"),
    ))->all();

    expect($indexes)->toContain(
        'intelligence_indicators:intel_indicators_run_severity_detected_idx',
        'civic_intelligence_runs:intel_runs_status_started_idx',
        'awards:awards_intel_status_bid_idx',
        'citizen_reports:citizen_reports_intel_open_project_idx',
    );
});
