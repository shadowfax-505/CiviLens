<?php

use App\Events\IntelligenceRuleExecuted;
use App\Models\IntelligenceIndicator;
use App\Models\IntelligenceRule;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function intelligenceAdmin(): User
{
    $group = PermissionGroup::query()->firstOrCreate(
        ['slug' => 'civic-data'],
        ['name' => 'Civic Data'],
    );
    $permission = Permission::query()->firstOrCreate(
        ['slug' => config('civiclens.permissions.intelligence_manage')],
        ['name' => 'Manage Intelligence', 'permission_group_id' => $group->id],
    );
    $role = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
    $role->permissions()->syncWithoutDetaching([$permission->id]);
    $user = User::factory()->create();
    $user->roles()->sync([$role->id]);

    return $user;
}

it('protects intelligence dashboard access', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.intelligence.index'))
        ->assertForbidden();
});

it('renders the intelligence dashboard for authorized users', function (): void {
    IntelligenceIndicator::factory()->create(['title' => 'Budget utilization signal']);

    $this->actingAs(intelligenceAdmin())
        ->get(route('admin.intelligence.index'))
        ->assertOk()
        ->assertSee('Intelligence Readiness')
        ->assertSee('Budget utilization signal');
});

it('filters indicators with url persistent query parameters', function (): void {
    IntelligenceIndicator::factory()->create(['title' => 'Critical project delay', 'severity' => 'critical']);
    IntelligenceIndicator::factory()->create(['title' => 'Document metadata gap', 'severity' => 'info']);

    $this->actingAs(intelligenceAdmin())
        ->get(route('admin.intelligence.indicators.index', ['severity' => 'critical', 'q' => 'delay']))
        ->assertOk()
        ->assertSee('Critical project delay')
        ->assertDontSee('Document metadata gap');
});

it('reviews an indicator through the protected workflow', function (): void {
    $indicator = IntelligenceIndicator::factory()->create(['status' => 'pending']);

    $this->actingAs(intelligenceAdmin())
        ->patch(route('admin.intelligence.indicators.review', $indicator), [
            'status' => 'needs_more_evidence',
            'notes' => 'Attach procurement timeline before accepting.',
        ])
        ->assertRedirect(route('admin.intelligence.indicators.show', $indicator));

    expect($indicator->refresh()->status)->toBe('needs_more_evidence');
});

it('runs rules and queues preparation jobs through admin routes', function (): void {
    Event::fake();
    $rule = IntelligenceRule::factory()->create(['slug' => 'document-missing-metadata', 'module' => 'documents']);

    $this->actingAs(intelligenceAdmin())
        ->post(route('admin.intelligence.rules.run', $rule))
        ->assertRedirect();

    $this->actingAs(intelligenceAdmin())
        ->post(route('admin.intelligence.processing-jobs.store'), [
            'target_type' => 'projects',
            'target_id' => 1,
            'job_type' => 'ocr_preparation',
        ])
        ->assertSessionHasErrors('target_id');

    Event::assertDispatched(IntelligenceRuleExecuted::class);
});
