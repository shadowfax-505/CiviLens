<?php

namespace Database\Factories;

use App\Models\AdministrativeUnion;
use App\Models\Upazila;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdministrativeUnion>
 */
class AdministrativeUnionFactory extends Factory
{
    protected $model = AdministrativeUnion::class;

    public function definition(): array
    {
        return [
            'upazila_id' => Upazila::factory(),
            'name' => fake()->unique()->streetName(),
            'type' => fake()->randomElement(['union', 'municipality']),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'geojson' => null,
        ];
    }
}
