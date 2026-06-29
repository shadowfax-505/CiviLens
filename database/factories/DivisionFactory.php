<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Division>
 */
class DivisionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'country_id' => Country::factory(),
            'name' => fake()->unique()->state(),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'geojson' => null,
        ];
    }
}
