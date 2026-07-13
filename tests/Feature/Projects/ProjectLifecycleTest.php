<?php

use App\Models\AdministrativeUnion;
use App\Models\Agency;
use App\Models\Country;
use App\Models\District;
use App\Models\Division;
use App\Models\FiscalYear;
use App\Models\FundingSource;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectCategory;
use App\Models\ProjectPriority;
use App\Models\ProjectStatus;
use App\Models\Role;
use App\Models\Upazila;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function projectAdmin(): User
{
    $role = Role::query()->create(['name' => 'Administrator', 'slug' => config('civiclens.roles.admin')]);
    $user = User::factory()->create();
    $user->roles()->attach($role);

    return $user;
}

function projectPayload(array $overrides = []): array
{
    $country = Country::factory()->create();
    $division = Division::factory()->for($country)->create(['name' => 'Dhaka']);
    $district = District::factory()->for($division)->create(['name' => 'Dhaka District']);
    $upazila = Upazila::factory()->for($district)->create(['name' => 'Savar']);
    $union = AdministrativeUnion::factory()->for($upazila, 'upazila')->create(['name' => 'Birulia']);
    $ward = Ward::factory()->for($union, 'union')->create(['name' => 'Ward 1']);
    $agency = Agency::factory()->for($country)->create();
    $category = ProjectCategory::factory()->create(['name' => 'Transport', 'slug' => 'transport']);
    $status = ProjectStatus::factory()->create(['name' => 'Planning', 'slug' => 'planning']);
    $priority = ProjectPriority::factory()->create(['name' => 'High', 'slug' => 'high']);
    $fundingSource = FundingSource::factory()->create(['name' => 'Public Funds', 'slug' => 'public-funds']);
    $fiscalYear = FiscalYear::factory()->create(['name' => 'FY 2026', 'starts_on' => '2025-07-01', 'ends_on' => '2026-06-30']);

    return array_merge([
        'project_code' => 'CVL-2026-001',
        'name' => 'Dhaka Road Improvement',
        'short_name' => 'DRI',
        'slug' => 'dhaka-road-improvement',
        'description' => 'Improve an arterial public road.',
        'agency_id' => $agency->id,
        'project_category_id' => $category->id,
        'project_status_id' => $status->id,
        'project_priority_id' => $priority->id,
        'funding_source_id' => $fundingSource->id,
        'fiscal_year_id' => $fiscalYear->id,
        'country_id' => $country->id,
        'division_id' => $division->id,
        'district_id' => $district->id,
        'upazila_id' => $upazila->id,
        'union_id' => $union->id,
        'ward_id' => $ward->id,
        'progress_percentage' => 15,
        'planned_start_date' => '2026-01-01',
        'planned_end_date' => '2026-12-31',
        'latitude' => '23.8103000',
        'longitude' => '90.4125000',
        'is_public' => '1',
        'is_active' => '1',
    ], $overrides);
}

it('restricts project administration to administrators', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/projects')->assertForbidden();
    $this->actingAs($user)->post('/admin/projects', [])->assertForbidden();
});

it('lets administrators create view update archive restore and delete projects', function (): void {
    $admin = projectAdmin();
    $payload = projectPayload();

    $this->actingAs($admin)->post('/admin/projects', $payload)->assertRedirect();

    $project = Project::query()->where('project_code', 'CVL-2026-001')->firstOrFail();

    expect($project->created_by)->toBe($admin->id)
        ->and($project->country_id)->toBe($payload['country_id'])
        ->and($project->division_id)->toBe($payload['division_id'])
        ->and($project->district_id)->toBe($payload['district_id'])
        ->and($project->upazila_id)->toBe($payload['upazila_id'])
        ->and($project->union_id)->toBe($payload['union_id'])
        ->and($project->ward_id)->toBe($payload['ward_id'])
        ->and(ProjectActivity::query()->where('project_id', $project->id)->where('event', 'created')->exists())->toBeTrue();

    $this->actingAs($admin)->get("/admin/projects/{$project->id}")
        ->assertOk()
        ->assertSee('Dhaka Road Improvement')
        ->assertSee('Planning')
        ->assertSee('Location Map');

    $newStatus = ProjectStatus::factory()->create(['name' => 'In Progress', 'slug' => 'in-progress']);

    $this->actingAs($admin)->put("/admin/projects/{$project->id}", array_merge($payload, [
        'name' => 'Dhaka Road Improvement Updated',
        'project_status_id' => $newStatus->id,
        'progress_percentage' => 45,
    ]))->assertRedirect();

    expect($project->fresh()->name)->toBe('Dhaka Road Improvement Updated')
        ->and($project->fresh()->updated_by)->toBe($admin->id)
        ->and(ProjectActivity::query()->where('project_id', $project->id)->where('event', 'status_changed')->exists())->toBeTrue()
        ->and(ProjectActivity::query()->where('project_id', $project->id)->where('event', 'progress_updated')->exists())->toBeTrue();

    $this->actingAs($admin)->patch("/admin/projects/{$project->id}/archive")->assertRedirect();
    expect($project->fresh()->archived_at)->not->toBeNull();

    $this->actingAs($admin)->patch("/admin/projects/{$project->id}/restore")->assertRedirect();
    expect($project->fresh()->archived_at)->toBeNull();

    $this->actingAs($admin)->delete("/admin/projects/{$project->id}")->assertRedirect();
    expect(Project::query()->find($project->id))->toBeNull()
        ->and(Project::withTrashed()->find($project->id)?->trashed())->toBeTrue();
});

it('shows the geographic location section on project create and edit forms', function (): void {
    $admin = projectAdmin();
    $project = Project::factory()->create();

    $this->actingAs($admin)->get('/admin/projects/create')
        ->assertOk()
        ->assertSee('Geographical Location')
        ->assertSee('Project map pin');

    $this->actingAs($admin)->get('/admin/projects/'.$project->id.'/edit')
        ->assertOk()
        ->assertSee('Geographical Location')
        ->assertSee('Project map pin');
});

it('supports advanced project search filters sorting and pagination', function (): void {
    $admin = projectAdmin();
    $payload = projectPayload();
    Project::factory()->create([
        'project_code' => 'ROAD-001',
        'name' => 'Rural Bridge Upgrade',
        'agency_id' => $payload['agency_id'],
        'project_category_id' => $payload['project_category_id'],
        'project_status_id' => $payload['project_status_id'],
        'project_priority_id' => $payload['project_priority_id'],
        'funding_source_id' => $payload['funding_source_id'],
        'fiscal_year_id' => $payload['fiscal_year_id'],
        'country_id' => $payload['country_id'],
        'progress_percentage' => 30,
        'planned_start_date' => '2026-02-01',
    ]);
    Project::factory()->create(['project_code' => 'WATER-001', 'name' => 'Water Treatment Plant', 'progress_percentage' => 80]);

    $query = http_build_query([
        'search' => 'Bridge',
        'project_code' => 'ROAD',
        'agency_id' => $payload['agency_id'],
        'project_category_id' => $payload['project_category_id'],
        'project_status_id' => $payload['project_status_id'],
        'project_priority_id' => $payload['project_priority_id'],
        'funding_source_id' => $payload['funding_source_id'],
        'fiscal_year_id' => $payload['fiscal_year_id'],
        'country_id' => $payload['country_id'],
        'progress_min' => 10,
        'progress_max' => 40,
        'planned_start_from' => '2026-01-01',
        'planned_start_to' => '2026-12-31',
        'sort' => 'name',
        'direction' => 'asc',
    ]);

    $this->actingAs($admin)->get('/admin/projects?'.$query)
        ->assertOk()
        ->assertSee('Rural Bridge Upgrade')
        ->assertDontSee('Water Treatment Plant');
});

it('validates project lookup relationships and numeric ranges', function (): void {
    $admin = projectAdmin();

    $this->actingAs($admin)->post('/admin/projects', [
        'project_code' => 'BAD-001',
        'name' => 'Invalid Project',
        'slug' => 'invalid-project',
        'agency_id' => 999,
        'project_category_id' => 999,
        'project_status_id' => 999,
        'project_priority_id' => 999,
        'funding_source_id' => 999,
        'fiscal_year_id' => 999,
        'progress_percentage' => 101,
    ])->assertSessionHasErrors([
        'agency_id',
        'project_category_id',
        'project_status_id',
        'project_priority_id',
        'funding_source_id',
        'fiscal_year_id',
        'progress_percentage',
    ]);
});
