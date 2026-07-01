<?php

use App\Jobs\GenerateAnalyticsReport;
use App\Jobs\GenerateAnalyticsSnapshot;
use App\Models\Budget;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function analyticsAdmin(): User
{
    $role = Role::query()->create(['name' => 'Administrator', 'slug' => config('civiclens.roles.admin')]);
    $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    $user->roles()->attach($role);

    return $user;
}

it('protects analytics dashboards from unauthorized users', function (): void {
    $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);

    $this->actingAs($user)->get('/admin/analytics')->assertForbidden();
    $this->actingAs($user)->get('/admin/analytics/metrics?metric=projects.active')->assertForbidden();
});

it('renders the executive analytics dashboard with filters and charts', function (): void {
    $admin = analyticsAdmin();
    Budget::factory()->create([
        'current_allocation' => 1000,
        'actual_expenditure' => 400,
    ]);

    $this->actingAs($admin)->get('/admin/analytics?dashboard=executive&date_from=2026-01-01&date_to=2026-12-31')
        ->assertOk()
        ->assertSee('Executive Decision Dashboard')
        ->assertSee('Budget Utilization')
        ->assertSee('Alerts and Recommendations')
        ->assertSee('data-chart-definition', false);
});

it('returns metric json and generates snapshots reports and alerts', function (): void {
    Queue::fake();
    $admin = analyticsAdmin();
    Budget::factory()->create([
        'current_allocation' => 2000,
        'actual_expenditure' => 1000,
    ]);

    $this->actingAs($admin)->get('/admin/analytics/metrics?metric=finance.budget_utilization')
        ->assertOk()
        ->assertJsonPath('key', 'finance.budget_utilization')
        ->assertJsonPath('value', 50);

    $this->actingAs($admin)->post('/admin/analytics/snapshots', [
        'period' => 'daily',
        'dashboard' => 'executive',
    ])->assertRedirect();

    $this->actingAs($admin)->post('/admin/analytics/reports', [
        'dashboard' => 'executive',
        'format' => 'csv',
    ])->assertRedirect();

    $this->actingAs($admin)->get('/admin/analytics/alerts')
        ->assertOk()
        ->assertSee('Analytics Alerts');

    Queue::assertPushed(GenerateAnalyticsSnapshot::class);
    Queue::assertPushed(GenerateAnalyticsReport::class);
});
