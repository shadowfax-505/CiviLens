<?php

use App\Models\Agency;
use App\Models\AgencyType;
use App\Models\Country;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function agencyAdmin(): User
{
    $role = Role::query()->create(['name' => 'Administrator', 'slug' => config('civiclens.roles.admin')]);
    $user = User::factory()->create();
    $user->roles()->attach($role);

    return $user;
}

it('restricts agency management to administrators', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/agencies')->assertForbidden();
    $this->actingAs($user)->post('/admin/agencies', ['name' => 'Roads Department'])->assertForbidden();
});

it('lets administrators create hierarchical agencies assigned to geography and users', function (): void {
    $admin = agencyAdmin();
    $country = Country::factory()->create(['name' => 'Bangladesh', 'iso2' => 'BD', 'iso3' => 'BGD']);
    $type = AgencyType::factory()->create(['name' => 'Ministry', 'slug' => 'ministry']);
    $childType = AgencyType::factory()->create(['name' => 'Department', 'slug' => 'department']);
    $staff = User::factory()->create();

    $this->actingAs($admin)->post('/admin/agencies', [
        'agency_type_id' => $type->id,
        'country_id' => $country->id,
        'name' => 'Ministry of Planning',
        'short_name' => 'MoP',
        'slug' => 'ministry-of-planning',
        'description' => 'National planning ministry.',
        'email' => 'planning@example.gov',
        'phone' => '+8801000000000',
        'website' => 'https://planning.example.gov',
        'address' => 'Sher-e-Bangla Nagar',
        'status' => 'active',
        'user_ids' => [$staff->id],
    ])->assertRedirect();

    $parent = Agency::query()->where('slug', 'ministry-of-planning')->firstOrFail();

    $this->actingAs($admin)->post('/admin/agencies', [
        'parent_id' => $parent->id,
        'agency_type_id' => $childType->id,
        'country_id' => $country->id,
        'name' => 'Implementation Monitoring Department',
        'short_name' => 'IMD',
        'slug' => 'implementation-monitoring-department',
        'status' => 'active',
    ])->assertRedirect();

    $child = Agency::query()->where('slug', 'implementation-monitoring-department')->firstOrFail();

    expect($parent->users()->whereKey($staff->id)->exists())->toBeTrue()
        ->and($child->parent->is($parent))->toBeTrue()
        ->and($child->country->iso2)->toBe('BD');
});

it('searches filters sorts paginates and soft deletes agencies', function (): void {
    $admin = agencyAdmin();
    $type = AgencyType::factory()->create(['name' => 'Department', 'slug' => 'department']);
    Agency::factory()->for($type)->create(['name' => 'Roads and Highways Department', 'status' => 'active']);
    Agency::factory()->for($type)->create(['name' => 'Archived Office', 'status' => 'archived']);

    $this->actingAs($admin)->get('/admin/agencies?search=Roads&status=active&agency_type_id='.$type->id.'&sort=name&direction=asc')
        ->assertOk()
        ->assertSee('Roads and Highways Department')
        ->assertDontSee('Archived Office');

    $agency = Agency::query()->where('name', 'Roads and Highways Department')->firstOrFail();

    $this->actingAs($admin)->delete("/admin/agencies/{$agency->id}")->assertRedirect();

    expect(Agency::query()->find($agency->id))->toBeNull()
        ->and(Agency::withTrashed()->find($agency->id)?->trashed())->toBeTrue();
});

it('validates agency relationships and status values', function (): void {
    $admin = agencyAdmin();

    $this->actingAs($admin)->post('/admin/agencies', [
        'agency_type_id' => 999,
        'name' => 'Broken Agency',
        'slug' => 'broken-agency',
        'status' => 'unknown',
    ])->assertSessionHasErrors(['agency_type_id', 'status']);
});
