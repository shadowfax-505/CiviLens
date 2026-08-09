<?php

use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('never reuses a country code another row already holds', function (): void {
    // Deterministic rather than probabilistic. Every two-letter code but one is
    // taken, so a factory that draws at random without consulting the table has
    // no way to succeed, while a correct one has exactly one answer.
    $codes = [];

    foreach (range('A', 'Z') as $first) {
        foreach (range('A', 'Z') as $second) {
            $codes[] = $first.$second;
        }
    }

    $reserved = 'QX';
    $rows = [];

    foreach (array_diff($codes, [$reserved]) as $index => $code) {
        $rows[] = [
            'name' => 'Country '.$index,
            'iso2' => $code,
            'iso3' => $code.'A',
            'phone_code' => '+1',
            'latitude' => 0,
            'longitude' => 0,
            'geojson' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    Country::query()->insert($rows);

    expect(Country::factory()->create()->iso2)->toBe($reserved);
});

it('does not collide with a country the seeder inserted', function (): void {
    // The suite seeds Bangladesh as BD. Faker's unique() remembers only what it
    // generated, so before this the factory could return BD and fail on the
    // unique index — roughly one run in a few hundred.
    Country::query()->create([
        'name' => 'Bangladesh',
        'iso2' => 'BD',
        'iso3' => 'BGD',
        'phone_code' => '+880',
        'latitude' => 23.685,
        'longitude' => 90.3563,
    ]);

    $created = Country::factory()->count(40)->create();

    expect($created->pluck('iso2'))->not->toContain('BD')
        ->and($created->pluck('iso2')->unique())->toHaveCount(40)
        ->and($created->pluck('iso3')->unique())->toHaveCount(40);
});
