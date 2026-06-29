<?php

namespace Database\Factories;

use App\Models\AgencyType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AgencyType>
 */
class AgencyTypeFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Ministry', 'Department', 'Regional Office', 'Local Office']).' '.fake()->unique()->word();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
        ];
    }
}
