<?php

namespace Database\Factories;

use App\Models\AdministrativeUnion;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ward>
 */
class WardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'union_id' => AdministrativeUnion::factory(),
            'name' => 'Ward '.fake()->unique()->numberBetween(1, 999),
            'code' => 'W-'.fake()->unique()->numberBetween(1, 9999),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'geojson' => null,
        ];
    }
}
