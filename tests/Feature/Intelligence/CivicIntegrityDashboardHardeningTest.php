<?php

use App\Models\Agency;
use App\Models\Budget;
use App\Models\CitizenReport;
use App\Models\CivicIntelligenceRun;
use App\Models\Document;
use App\Models\IntelligenceEvidence;
use App\Models\IntelligenceIndicator;
use App\Models\IntelligenceRule;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function sprint13IntegrityAdmin(): User
{
    $role = Role::query()->firstOrCreate(['slug' => config('civiclens.roles.admin')], ['name' => 'Administrator']);
    $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    $user->roles()->sync([$role->id]);

    return $user;
}

it('renders integrity history rankings distributions and performance panels', function (): void {
    $admin = sprint13IntegrityAdmin();
    $agency = Agency::factory()->create(['name' => 'Atlas Works Agency']);
    $project = Project::factory()->create(['agency_id' => $agency->id, 'name' => 'Atlas Bridge']);
    Budget::factory()->create(['project_id' => $project->id, 'current_allocation' => 120000, 'actual_expenditure' => 119000]);
    Document::factory()->create(['title' => 'Atlas completion certificate']);
    CitizenReport::factory()->count(2)->create(['agency_id' => $agency->id, 'project_id' => $project->id, 'resolved_at' => null]);
    CivicIntelligenceRun::query()->create([
        'engine_version' => '13.1.0',
        'status' => 'completed',
        'started_at' => now()->subMinutes(10),
        'completed_at' => now()->subMinutes(9),
        'rules_executed' => 3,
        'indicators_created' => 2,
        'summary_payload' => ['duration_ms' => 1200],
    ]);
    IntelligenceIndicator::factory()->create([
        'source_type' => Project::class,
        'source_id' => $project->id,
        'module' => 'projects',
        'severity' => 'critical',
        'status' => 'pending',
        'metadata' => ['engine_run_id' => 1, 'engine_version' => '13.1.0'],
    ]);

    $this->actingAs($admin)->get('/admin/intelligence')
        ->assertOk()
        ->assertSee('Integrity Timeline')
        ->assertSee('Rule Execution History')
        ->assertSee('Indicator Distribution')
        ->assertSee('Agency Risk Ranking')
        ->assertSee('Document Completeness')
        ->assertSee('Citizen Report Correlations')
        ->assertSee('Performance Metrics');
});

it('exposes full explainability fields for an indicator', function (): void {
    $admin = sprint13IntegrityAdmin();
    $rule = IntelligenceRule::factory()->create([
        'name' => 'Budget overrun risk',
        'thresholds' => ['warning' => 80, 'critical' => 95],
        'version' => '13.1.0',
    ]);
    $indicator = IntelligenceIndicator::factory()->create([
        'intelligence_rule_id' => $rule->id,
        'title' => 'Budget overrun risk detected',
        'confidence_score' => 95,
        'detection_payload' => ['actual_value' => 99, 'expected_value' => 80, 'recommendation' => 'Review supporting budget transactions.'],
        'metadata' => ['engine_version' => '13.1.0', 'engine_run_id' => 7],
    ]);
    IntelligenceEvidence::factory()->create([
        'intelligence_indicator_id' => $indicator->id,
        'label' => 'Budget source',
        'payload' => ['actual_value' => 99, 'expected_value' => 80],
    ]);

    $this->actingAs($admin)->get("/admin/intelligence/indicators/{$indicator->id}")
        ->assertOk()
        ->assertSee('Triggered Rule')
        ->assertSee('Threshold')
        ->assertSee('Actual Value')
        ->assertSee('Expected Value')
        ->assertSee('Engine Version')
        ->assertSee('Recommendation')
        ->assertSee('Human review required');
});
