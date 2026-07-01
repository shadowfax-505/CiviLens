<?php

use App\Models\AnalyticsReport;
use App\Models\Budget;
use App\Models\Role;
use App\Models\User;
use App\Services\Analytics\ReportBuilder;
use App\Support\Analytics\AnalyticsFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function sprint13ReportAdmin(): User
{
    $role = Role::query()->firstOrCreate(['slug' => config('civiclens.roles.admin')], ['name' => 'Administrator']);
    $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    $user->roles()->sync([$role->id]);

    return $user;
}

it('generates branded downloadable csv spreadsheet and pdf compatible reports', function (): void {
    $admin = sprint13ReportAdmin();
    Budget::factory()->create(['current_allocation' => 1000, 'actual_expenditure' => 500]);

    foreach (['csv', 'xlsx', 'pdf'] as $format) {
        $report = app(ReportBuilder::class)->generate('executive', $format, AnalyticsFilters::fromArray([]), $admin);

        expect($report)->toBeInstanceOf(AnalyticsReport::class)
            ->and($report->status)->toBe('generated')
            ->and($report->payload)->toHaveKeys(['metrics', 'charts', 'generated_at', 'branding', 'evidence']);

        $response = $this->actingAs($admin)->get("/admin/analytics/reports/{$report->id}/download");
        $response->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="'.$report->uuid.'.'.$format.'"');

        expect($response->getContent())->toContain('CivicLens');
    }
});

it('queues report generation through the existing analytics report route for all supported formats', function (): void {
    $admin = sprint13ReportAdmin();

    foreach (['csv', 'xlsx', 'pdf'] as $format) {
        $this->actingAs($admin)->post('/admin/analytics/reports', [
            'dashboard' => 'executive',
            'format' => $format,
        ])->assertRedirect();
    }

    expect(AnalyticsReport::query()->count())->toBe(3);
});
