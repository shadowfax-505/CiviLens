<?php

use App\Models\CivicIntelligenceRun;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function sprint13MonitoringAdmin(): User
{
    $role = Role::query()->firstOrCreate(['slug' => config('civiclens.roles.admin')], ['name' => 'Administrator']);
    $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    $user->roles()->sync([$role->id]);

    return $user;
}

it('exposes public safe version and enriched health readiness data', function (): void {
    CivicIntelligenceRun::query()->create([
        'engine_version' => '13.1.0',
        'status' => 'completed',
        'started_at' => now()->subMinutes(3),
        'completed_at' => now()->subMinutes(2),
        'rules_executed' => 5,
        'indicators_created' => 4,
    ]);

    $this->getJson('/version')
        ->assertOk()
        ->assertHeader('X-Request-Id')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertJsonPath('version', 'v1.0.0')
        ->assertJsonStructure(['app', 'version', 'environment', 'commit', 'generated_at'])
        ->assertJsonMissingPath('app_key');

    $this->getJson('/healthz')
        ->assertOk()
        ->assertHeader('X-Request-Id')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertJsonPath('checks.scheduler.configured', true)
        ->assertJsonPath('checks.integrity.last_run.status', 'completed')
        ->assertJsonMissingPath('env');
});

it('exposes admin-only operational metrics', function (): void {
    $admin = sprint13MonitoringAdmin();

    CivicIntelligenceRun::query()->create([
        'engine_version' => '13.1.0',
        'status' => 'completed',
        'started_at' => now()->subMinutes(3),
        'completed_at' => now()->subMinutes(2),
        'rules_executed' => 5,
        'indicators_created' => 4,
    ]);

    $this->getJson('/admin/system/metrics')->assertRedirect('/login');

    $this->actingAs($admin)->getJson('/admin/system/metrics')
        ->assertOk()
        ->assertJsonPath('integrity.runs_total', 1)
        ->assertJsonPath('ingestion.ready', true)
        ->assertJsonStructure([
            'application',
            'database',
            'cache',
            'queue',
            'scheduler',
            'integrity',
            'ingestion',
            'generated_at',
        ]);
});
