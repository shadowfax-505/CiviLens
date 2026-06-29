<?php

use App\Models\AdministrativeUnion;
use App\Models\Country;
use App\Models\District;
use App\Models\Division;
use App\Models\Role;
use App\Models\Upazila;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function geographyAdmin(): User
{
    $role = Role::query()->create(['name' => 'Administrator', 'slug' => config('civiclens.roles.admin')]);
    $user = User::factory()->create();
    $user->roles()->attach($role);

    return $user;
}

it('restricts geographic reference data to administrators', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/geography/countries')->assertForbidden();
    $this->actingAs($user)->post('/admin/geography/countries', ['name' => 'Bangladesh', 'iso2' => 'BD', 'iso3' => 'BGD'])->assertForbidden();
});

it('lets administrators create the normalized geographic hierarchy', function (): void {
    $admin = geographyAdmin();

    $this->actingAs($admin)->post('/admin/geography/countries', [
        'name' => 'Bangladesh',
        'iso2' => 'BD',
        'iso3' => 'BGD',
        'phone_code' => '+880',
        'latitude' => '23.6850000',
        'longitude' => '90.3563000',
    ])->assertRedirect();

    $country = Country::query()->where('iso2', 'BD')->firstOrFail();

    $this->actingAs($admin)->post('/admin/geography/divisions', [
        'country_id' => $country->id,
        'name' => 'Dhaka',
        'code' => 'DHK',
    ])->assertRedirect();

    $division = Division::query()->where('name', 'Dhaka')->firstOrFail();

    $this->actingAs($admin)->post('/admin/geography/districts', [
        'division_id' => $division->id,
        'name' => 'Dhaka District',
        'code' => 'DHK-D',
    ])->assertRedirect();

    $district = District::query()->where('name', 'Dhaka District')->firstOrFail();

    $this->actingAs($admin)->post('/admin/geography/upazilas', [
        'district_id' => $district->id,
        'name' => 'Savar',
        'code' => 'SAV',
    ])->assertRedirect();

    $upazila = Upazila::query()->where('name', 'Savar')->firstOrFail();

    $this->actingAs($admin)->post('/admin/geography/unions', [
        'upazila_id' => $upazila->id,
        'name' => 'Birulia',
        'type' => 'union',
        'code' => 'BIR',
    ])->assertRedirect();

    $union = AdministrativeUnion::query()->where('name', 'Birulia')->firstOrFail();

    $this->actingAs($admin)->post('/admin/geography/wards', [
        'union_id' => $union->id,
        'name' => 'Ward 1',
        'code' => 'W-1',
    ])->assertRedirect();

    expect(Ward::query()->where('name', 'Ward 1')->exists())->toBeTrue()
        ->and($union->upazila->district->division->country->iso2)->toBe('BD');
});

it('searches filters sorts paginates and soft deletes countries', function (): void {
    $admin = geographyAdmin();
    Country::factory()->create(['name' => 'Bangladesh', 'iso2' => 'BD', 'iso3' => 'BGD']);
    Country::factory()->create(['name' => 'Canada', 'iso2' => 'CA', 'iso3' => 'CAN']);

    $this->actingAs($admin)->get('/admin/geography/countries?search=Bangladesh&sort=name&direction=asc')
        ->assertOk()
        ->assertSee('Bangladesh')
        ->assertDontSee('Canada');

    $country = Country::query()->where('iso2', 'BD')->firstOrFail();

    $this->actingAs($admin)->delete("/admin/geography/countries/{$country->id}")
        ->assertRedirect();

    expect(Country::query()->find($country->id))->toBeNull()
        ->and(Country::withTrashed()->find($country->id)?->trashed())->toBeTrue();
});

it('validates parent geography before creating child records', function (): void {
    $admin = geographyAdmin();

    $this->actingAs($admin)->post('/admin/geography/divisions', [
        'country_id' => 999,
        'name' => 'Invalid Division',
    ])->assertSessionHasErrors('country_id');
});

it('allows administrators to update child geography without renaming it', function (): void {
    $admin = geographyAdmin();
    $country = Country::factory()->create();
    $division = Division::factory()->for($country)->create(['name' => 'Dhaka', 'code' => 'DHK']);

    $this->actingAs($admin)->put("/admin/geography/divisions/{$division->id}", [
        'country_id' => $country->id,
        'name' => 'Dhaka',
        'code' => 'DHA',
    ])->assertRedirect();

    expect($division->fresh()->code)->toBe('DHA');
});
