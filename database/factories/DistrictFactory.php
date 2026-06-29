<?php

namespace Database\Factories;

use App\Models\District;
use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<District>
 */
class DistrictFactory extends Factory
{
    public function definition(): array
    {
        return [
            'division_id' => Division::factory(),
            'name' => fake()->unique()->city().' District',
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'geojson' => null,
        ];
    }
}
