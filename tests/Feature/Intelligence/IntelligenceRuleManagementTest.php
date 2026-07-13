<?php

use App\Models\BidSubmission;
use App\Models\IntelligenceRule;
use App\Models\Role;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function sprint13RuleAdmin(): User
{
    $role = Role::query()->firstOrCreate(['slug' => config('civiclens.roles.admin')], ['name' => 'Administrator']);
    $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    $user->roles()->sync([$role->id]);

    return $user;
}

it('renders an administrative rule management console', function (): void {
    $admin = sprint13RuleAdmin();
    IntelligenceRule::factory()->create([
        'name' => 'Repeat winner concentration',
        'slug' => 'procurement-repeat-winner-concentration',
        'thresholds' => ['warning' => 2, 'critical' => 4],
    ]);

    $this->actingAs($admin)->get('/admin/intelligence/rules')
        ->assertOk()
        ->assertSee('Rule Management Console')
        ->assertSee('Repeat winner concentration')
        ->assertSee('Dry Run');
});

it('allows authorized admins to update rule settings and records audit history', function (): void {
    $admin = sprint13RuleAdmin();
    $rule = IntelligenceRule::factory()->create([
        'thresholds' => ['warning' => 2, 'critical' => 4],
        'is_active' => true,
    ]);

    $this->actingAs($admin)->patch("/admin/intelligence/rules/{$rule->id}", [
        'is_active' => '0',
        'priority' => '25',
        'weight' => '80',
        'severity_default' => 'warning',
        'thresholds' => '{"warning":4,"critical":8}',
        'description' => 'Updated deterministic rule documentation.',
        'documentation_url' => 'https://example.com/rules/repeat-winner',
        'execution_frequency' => 'daily',
    ])->assertRedirect("/admin/intelligence/rules/{$rule->id}");

    $rule->refresh();

    expect($rule->is_active)->toBeFalse()
        ->and($rule->priority)->toBe(25)
        ->and($rule->weight)->toBe(80)
        ->and($rule->thresholds)->toBe(['warning' => 4, 'critical' => 8]);

    $this->assertDatabaseHas('intelligence_rule_audits', [
        'intelligence_rule_id' => $rule->id,
        'actor_id' => $admin->id,
        'event' => 'rule.updated',
    ]);
});

it('dry-runs a rule without persisting generated indicators', function (): void {
    $admin = sprint13RuleAdmin();
    $rule = IntelligenceRule::factory()->create([
        'slug' => 'project-delay-risk',
        'thresholds' => ['warning' => 30, 'critical' => 90, 'max_progress' => 90],
    ]);

    $this->actingAs($admin)->postJson("/admin/intelligence/rules/{$rule->id}/dry-run")
        ->assertOk()
        ->assertJsonPath('rule.slug', 'project-delay-risk')
        ->assertJsonPath('dry_run', true)
        ->assertJsonStructure(['estimated_matches', 'thresholds', 'explanation']);
});

it('dry-runs single bid procurement rules with portable relationship counts', function (): void {
    $admin = sprint13RuleAdmin();
    $rule = IntelligenceRule::factory()->create([
        'name' => 'Single bid procurement signal',
        'slug' => 'procurement-single-bid-risk',
        'module' => 'procurement',
    ]);
    Tender::factory()->create(['title' => 'Zero bid tender']);
    $singleBidTender = Tender::factory()->create(['title' => 'Single bid tender']);
    $multiBidTender = Tender::factory()->create(['title' => 'Multi bid tender']);

    BidSubmission::factory()->create(['tender_id' => $singleBidTender->id]);
    BidSubmission::factory()->count(2)->create(['tender_id' => $multiBidTender->id]);

    $this->actingAs($admin)->postJson("/admin/intelligence/rules/{$rule->id}/dry-run")
        ->assertOk()
        ->assertJsonPath('rule.slug', 'procurement-single-bid-risk')
        ->assertJsonPath('estimated_matches', 1);

    $this->actingAs($admin)->post("/admin/intelligence/rules/{$rule->id}/dry-run")
        ->assertRedirect()
        ->assertSessionHas('status', 'Dry run for Single bid procurement signal estimated 1 matching source record(s).');
});
