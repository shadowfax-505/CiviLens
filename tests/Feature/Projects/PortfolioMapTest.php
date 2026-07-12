<?php

use App\Models\Country;
use App\Models\Division;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function portfolioMapAdmin(): User
{
    $role = Role::query()->create(['name' => 'Administrator', 'slug' => config('civiclens.roles.admin')]);
    $user = User::factory()->create();
    $user->roles()->attach($role);

    return $user;
}

it('returns only public-safe markers for published projects with complete coordinates', function (): void {
    $visible = Project::factory()->create([
        'name' => 'Visible Bridge',
        'is_public' => true,
        'is_active' => true,
        'latitude' => '23.8103000',
        'longitude' => '90.4125000',
    ]);
    Project::factory()->create(['name' => 'Internal Scheme', 'is_public' => false, 'latitude' => '23.8103000', 'longitude' => '90.4125000']);
    Project::factory()->create(['name' => 'Unlocated Scheme', 'is_public' => true, 'latitude' => '23.8103000', 'longitude' => null]);

    $this->getJson('/public/projects/map-data?west=90&south=23&east=91&north=24')
        ->assertOk()
        ->assertJsonPath('meta.count', 1)
        ->assertJsonPath('data.0.name', 'Visible Bridge')
        ->assertJsonPath('data.0.url', route('public.projects.show', $visible))
        ->assertJsonMissingPath('data.0.description')
        ->assertJsonMissingPath('data.0.is_public');
});

it('validates public map viewports and caps returned markers', function (): void {
    config()->set('civiclens.maps.public_marker_limit', 1);
    Project::factory()->count(2)->create(['is_public' => true, 'is_active' => true, 'latitude' => '23.8103000', 'longitude' => '90.4125000']);

    $this->getJson('/public/projects/map-data?west=90&south=23&east=91&north=24')
        ->assertOk()
        ->assertJsonPath('meta.count', 1)
        ->assertJsonPath('meta.capped', true);

    $this->getJson('/public/projects/map-data?west=90&south=23&east=91&north=91')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('north');
});

it('requires project-view authorization for internal map markers', function (): void {
    $project = Project::factory()->create(['name' => 'Internal Scheme', 'is_public' => false, 'latitude' => '23.8103000', 'longitude' => '90.4125000']);

    $this->getJson('/admin/projects/map-data')->assertRedirect(route('login'));

    $this->actingAs(portfolioMapAdmin())->getJson('/admin/projects/map-data')
        ->assertOk()
        ->assertJsonPath('data.0.id', $project->id)
        ->assertJsonPath('data.0.name', 'Internal Scheme');
});

it('rejects partial coordinate pairs and mismatched project geography', function (): void {
    $admin = portfolioMapAdmin();
    $project = Project::factory()->create(['latitude' => null, 'longitude' => null]);
    $country = Country::factory()->create();
    $otherCountry = Country::factory()->create();
    $division = Division::factory()->for($country)->create();

    $this->actingAs($admin)->patch('/admin/projects/map/'.$project->id, [
        'country_id' => $otherCountry->id,
        'division_id' => $division->id,
        'latitude' => '23.8103000',
    ])->assertSessionHasErrors(['latitude', 'longitude', 'division_id']);
});
