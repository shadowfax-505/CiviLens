<?php

use App\Models\Agency;
use App\Models\AgencyType;
use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('supports agency hierarchy type location and user assignments', function (): void {
    $country = Country::factory()->create();
    $type = AgencyType::factory()->create(['name' => 'Ministry', 'slug' => 'ministry']);
    $parent = Agency::factory()->for($type)->for($country)->create(['name' => 'Ministry of Planning']);
    $child = Agency::factory()->for($type)->for($country)->for($parent, 'parent')->create(['name' => 'Planning Department']);
    $user = User::factory()->create();

    $parent->users()->attach($user, ['relationship' => 'administrator']);

    expect($child->parent->is($parent))->toBeTrue()
        ->and($parent->children()->whereKey($child)->exists())->toBeTrue()
        ->and($parent->type->is($type))->toBeTrue()
        ->and($parent->country->is($country))->toBeTrue()
        ->and($parent->users()->whereKey($user)->first()?->pivot->relationship)->toBe('administrator');
});
