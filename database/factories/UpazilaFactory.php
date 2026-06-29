<?php

namespace Database\Factories;

use App\Models\District;
use App\Models\Upazila;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Upazila>
 */
class UpazilaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'district_id' => District::factory(),
            'name' => fake()->unique()->city(),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'geojson' => null,
        ];
    }
}
