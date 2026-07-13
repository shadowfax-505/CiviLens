<?php

use App\Models\Award;
use App\Models\BidderOrganization;
use App\Models\BidSubmission;
use App\Models\Budget;
use App\Models\CitizenReport;
use App\Models\CivicIntelligenceRun;
use App\Models\IntelligenceIndicator;
use App\Models\IntelligenceRule;
use App\Models\ProcurementMethod;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tender;
use App\Models\TenderCategory;
use App\Models\TenderStatus;
use App\Models\User;
use App\Services\Intelligence\CivicIntegrityEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function civicIntegrityAdmin(): User
{
    $role = Role::query()->firstOrCreate(['slug' => config('civiclens.roles.admin')], ['name' => 'Administrator']);
    $user = User::factory()->create();
    $user->roles()->sync([$role->id]);

    return $user;
}

it('runs deterministic civic integrity analysis with evidence and reproducible run metadata', function (): void {
    $admin = civicIntegrityAdmin();
    $project = Project::factory()->create([
        'project_code' => 'PRJ-ATLAS-001',
        'name' => 'Market access road',
        'short_name' => 'MAR',
        'slug' => 'market-access-road-atlas',
    ]);
    $bidder = BidderOrganization::factory()->create(['name' => 'Atlas Construction']);
    $budget = Budget::factory()->create(['project_id' => $project->id]);
    $method = ProcurementMethod::factory()->create();
    $category = TenderCategory::factory()->create();
    $status = TenderStatus::factory()->create();

    foreach (range(1, 3) as $index) {
        $tender = Tender::query()->create([
            'project_id' => $project->id,
            'budget_id' => $budget->id,
            'agency_id' => $project->agency_id,
            'procurement_method_id' => $method->id,
            'tender_category_id' => $category->id,
            'tender_status_id' => $status->id,
            'tender_number' => 'TDR-ATLAS-'.$index,
            'title' => 'Atlas tender '.$index,
            'slug' => 'atlas-tender-'.$index,
            'description' => 'Deterministic test tender.',
            'closing_at' => now()->addMonth(),
            'is_public' => true,
            'is_active' => true,
        ]);
        $bid = BidSubmission::factory()->create([
            'tender_id' => $tender->id,
            'bidder_organization_id' => $bidder->id,
        ]);
        Award::factory()->create([
            'tender_id' => $tender->id,
            'bid_submission_id' => $bid->id,
            'status' => 'approved',
        ]);
    }

    CitizenReport::factory()->count(3)->create([
        'project_id' => $project->id,
        'agency_id' => $project->agency_id,
        'resolved_at' => null,
    ]);

    IntelligenceRule::factory()->create([
        'slug' => 'procurement-repeat-winner-concentration',
        'name' => 'Repeat winner concentration',
        'module' => 'procurement',
        'thresholds' => ['warning' => 2, 'critical' => 3],
        'version' => '13.1.0',
    ]);
    IntelligenceRule::factory()->create([
        'slug' => 'citizen-report-cluster',
        'name' => 'Citizen report cluster',
        'module' => 'public',
        'thresholds' => ['warning' => 2, 'critical' => 4],
        'version' => '13.1.0',
    ]);

    $run = app(CivicIntegrityEngineService::class)->run($admin);

    expect($run)->toBeInstanceOf(CivicIntelligenceRun::class)
        ->and($run->status)->toBe('completed')
        ->and($run->rules_executed)->toBe(2)
        ->and($run->indicators_created)->toBeGreaterThanOrEqual(2)
        ->and($run->threshold_snapshot)->toHaveKeys([
            'procurement-repeat-winner-concentration',
            'citizen-report-cluster',
        ]);

    $indicator = IntelligenceIndicator::query()
        ->where('metadata->engine_run_id', $run->id)
        ->where('metadata->rule_slug', 'procurement-repeat-winner-concentration')
        ->firstOrFail();

    expect($indicator->evidence()->exists())->toBeTrue()
        ->and($indicator->description)->not->toContain('corruption')
        ->and($indicator->detection_payload)->toHaveKeys(['award_count', 'thresholds']);
});

it('runs the single bid rule without sqlite having clause failures', function (): void {
    $admin = civicIntegrityAdmin();
    $rule = IntelligenceRule::factory()->create([
        'slug' => 'procurement-single-bid-risk',
        'name' => 'Single bid procurement signal',
        'module' => 'procurement',
        'version' => '13.1.0',
    ]);
    $tender = Tender::factory()->create(['title' => 'Single bidder tender']);
    BidSubmission::factory()->create(['tender_id' => $tender->id]);

    $this->actingAs($admin)->post('/admin/intelligence/engine/run')
        ->assertRedirect()
        ->assertSessionHas('status');

    $run = CivicIntelligenceRun::query()->latest()->firstOrFail();

    expect($run->status)->toBe('completed')
        ->and($run->rules_executed)->toBe(1)
        ->and($run->indicators_created)->toBe(1);

    $this->assertDatabaseHas('intelligence_indicators', [
        'intelligence_rule_id' => $rule->id,
        'source_id' => $tender->id,
    ]);
});

it('exposes a production health endpoint without leaking sensitive configuration', function (): void {
    $this->getJson('/healthz')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonStructure([
            'status',
            'checks' => [
                'app',
                'database',
                'cache',
                'storage',
                'queue',
            ],
        ])
        ->assertJsonMissingPath('checks.database.password')
        ->assertJsonMissingPath('env');
});
