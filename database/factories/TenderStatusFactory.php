<?php

namespace Database\Factories;

use App\Models\TenderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TenderStatus>
 */
class TenderStatusFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 9999),
            'description' => fake()->sentence(),
            'sort_order' => fake()->numberBetween(1, 99),
            'is_active' => true,
        ];
    }
}
