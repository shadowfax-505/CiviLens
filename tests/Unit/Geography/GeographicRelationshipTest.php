<?php

use App\Models\AdministrativeUnion;
use App\Models\Country;
use App\Models\District;
use App\Models\Division;
use App\Models\Upazila;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('walks the normalized geographic hierarchy from ward to country', function (): void {
    $country = Country::factory()->create(['name' => 'Bangladesh', 'iso2' => 'BD', 'iso3' => 'BGD']);
    $division = Division::factory()->for($country)->create(['name' => 'Dhaka']);
    $district = District::factory()->for($division)->create(['name' => 'Dhaka District']);
    $upazila = Upazila::factory()->for($district)->create(['name' => 'Savar']);
    $union = AdministrativeUnion::factory()->for($upazila, 'upazila')->create(['name' => 'Birulia']);
    $ward = Ward::factory()->for($union, 'union')->create(['name' => 'Ward 1']);

    expect($ward->union->upazila->district->division->country->is($country))->toBeTrue()
        ->and($country->divisions()->whereKey($division)->exists())->toBeTrue()
        ->and($division->districts()->whereKey($district)->exists())->toBeTrue()
        ->and($district->upazilas()->whereKey($upazila)->exists())->toBeTrue()
        ->and($upazila->unions()->whereKey($union)->exists())->toBeTrue()
        ->and($union->wards()->whereKey($ward)->exists())->toBeTrue();
});
