<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    public function definition(): array
    {
        $iso2 = fake()->unique()->lexify('??');

        return [
            'name' => fake()->unique()->country(),
            'iso2' => strtoupper($iso2),
            'iso3' => strtoupper($iso2.fake()->unique()->lexify('?')),
            'phone_code' => '+'.fake()->numberBetween(1, 999),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'geojson' => null,
        ];
    }
}
