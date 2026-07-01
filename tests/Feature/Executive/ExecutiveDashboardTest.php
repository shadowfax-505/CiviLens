<?php

use App\Models\AnalyticsAlert;
use App\Models\Budget;
use App\Models\CitizenReport;
use App\Models\CivicIntelligenceRun;
use App\Models\Document;
use App\Models\IntelligenceIndicator;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function sprint13ExecutiveAdmin(): User
{
    $role = Role::query()->firstOrCreate(['slug' => config('civiclens.roles.admin')], ['name' => 'Administrator']);
    $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    $user->roles()->sync([$role->id]);

    return $user;
}

it('shows an authorized executive command center with cross module production signals', function (): void {
    $admin = sprint13ExecutiveAdmin();
    Project::factory()->create();
    Budget::factory()->create(['current_allocation' => 100000, 'actual_expenditure' => 65000]);
    Tender::factory()->create();
    Document::factory()->create();
    CitizenReport::factory()->create(['submitted_at' => now()]);
    IntelligenceIndicator::factory()->create(['severity' => 'warning', 'status' => 'pending']);
    AnalyticsAlert::factory()->create(['severity' => 'warning', 'status' => 'open']);
    CivicIntelligenceRun::query()->create([
        'engine_version' => '13.1.0',
        'status' => 'completed',
        'started_at' => now()->subMinutes(5),
        'completed_at' => now(),
        'rules_executed' => 4,
        'indicators_created' => 2,
    ]);

    $this->actingAs($admin)->get('/dashboard')
        ->assertOk()
        ->assertSee('Executive Command Center')
        ->assertSee('System Health')
        ->assertSee('Recent Integrity Runs')
        ->assertSee('Risk Summary')
        ->assertSee('Search Analytics');
});

it('requires authentication for the executive command center', function (): void {
    $this->get('/dashboard')->assertRedirect('/login');
});
