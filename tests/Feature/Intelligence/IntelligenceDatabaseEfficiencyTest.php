<?php

use App\Models\Award;
use App\Models\BidderOrganization;
use App\Models\BidSubmission;
use App\Models\Budget;
use App\Models\CitizenReport;
use App\Models\IntelligenceIndicator;
use App\Models\IntelligenceRule;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tender;
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

it('orders capped execution candidates by utilization with an identifier tie-breaker', function (): void {
    $rule = IntelligenceRule::factory()->create([
        'slug' => 'low-budget-utilization',
        'module' => 'finance',
        'thresholds' => ['max_utilization' => 30, 'warning' => 50, 'critical' => 90],
    ]);
    $project = Project::factory()->create();

    $budgets = collect(range(26, 1))->map(fn (int $expenditure): Budget => Budget::factory()->create([
        'project_id' => $project->id,
        'current_allocation' => $expenditure * 10,
        'actual_expenditure' => $expenditure,
    ]));

    $indicators = app(IntelligenceManager::class)->runRule($rule);

    expect($indicators)->toHaveCount(25)
        ->and($indicators->pluck('source_id')->all())->toBe(
            $budgets->take(25)->pluck('id')->all(),
        );
});

it('reports dry-run execution cap metadata without changing estimated matches', function (): void {
    $rule = IntelligenceRule::factory()->create([
        'slug' => 'low-budget-utilization',
        'module' => 'finance',
        'thresholds' => ['max_utilization' => 30, 'warning' => 50, 'critical' => 90],
    ]);
    $project = Project::factory()->create();

    foreach (range(1, 26) as $expenditure) {
        Budget::factory()->create([
            'project_id' => $project->id,
            'current_allocation' => 100,
            'actual_expenditure' => $expenditure,
        ]);
    }

    $dryRun = app(RuleManagementService::class)->dryRun($rule);

    expect($dryRun['estimated_matches'])->toBe(26)
        ->and(data_get($dryRun, 'execution_limit'))->toBe(25)
        ->and(data_get($dryRun, 'execution_candidate_count'))->toBe(25)
        ->and(data_get($dryRun, 'execution_candidate_count_truncated'))->toBeTrue();
});

it('excludes soft-deleted projects from citizen report cluster candidates', function (): void {
    $rule = IntelligenceRule::factory()->create([
        'slug' => 'citizen-report-cluster',
        'module' => 'public',
        'thresholds' => ['warning' => 2, 'critical' => 4],
    ]);
    $activeProject = Project::factory()->create();
    $deletedProject = Project::factory()->create();

    CitizenReport::factory()->count(2)->create(['project_id' => $activeProject->id, 'agency_id' => $activeProject->agency_id]);
    CitizenReport::factory()->count(2)->create(['project_id' => $deletedProject->id, 'agency_id' => $deletedProject->agency_id]);
    $deletedProject->delete();

    $dryRun = app(RuleManagementService::class)->dryRun($rule);
    $indicators = app(IntelligenceManager::class)->runRule($rule);

    expect($dryRun['estimated_matches'])->toBe(1)
        ->and($dryRun['execution_candidate_count'])->toBe(1)
        ->and($indicators)->toHaveCount(1)
        ->and($indicators->sole()->source_id)->toBe($activeProject->id);
});

it('excludes soft-deleted organizations from repeat winner candidates', function (): void {
    $rule = IntelligenceRule::factory()->create([
        'slug' => 'procurement-repeat-winner-concentration',
        'module' => 'procurement',
        'thresholds' => ['warning' => 2, 'critical' => 4],
    ]);
    $activeBidder = BidderOrganization::query()->create([
        'name' => 'Active review bidder',
        'slug' => 'active-review-bidder',
        'status' => 'active',
    ]);
    $deletedBidder = BidderOrganization::query()->create([
        'name' => 'Deleted review bidder',
        'slug' => 'deleted-review-bidder',
        'status' => 'active',
    ]);
    $tender = Tender::factory()->create();

    foreach (range(1, 2) as $index) {
        $activeBid = BidSubmission::query()->create([
            'tender_id' => $tender->id,
            'bidder_organization_id' => $activeBidder->id,
            'reference_number' => 'ACTIVE-BID-'.$index,
            'submitted_at' => now(),
        ]);
        $deletedBid = BidSubmission::query()->create([
            'tender_id' => $tender->id,
            'bidder_organization_id' => $deletedBidder->id,
            'reference_number' => 'DELETED-BID-'.$index,
            'submitted_at' => now(),
        ]);
        Award::query()->create(['tender_id' => $tender->id, 'bid_submission_id' => $activeBid->id, 'status' => 'approved']);
        Award::query()->create(['tender_id' => $tender->id, 'bid_submission_id' => $deletedBid->id, 'status' => 'approved']);
    }
    $deletedBidder->delete();

    $dryRun = app(RuleManagementService::class)->dryRun($rule);
    $indicators = app(IntelligenceManager::class)->runRule($rule);

    expect($dryRun['estimated_matches'])->toBe(1)
        ->and($dryRun['execution_candidate_count'])->toBe(1)
        ->and($indicators)->toHaveCount(1)
        ->and($indicators->sole()->source_id)->toBe($activeBidder->id);
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
