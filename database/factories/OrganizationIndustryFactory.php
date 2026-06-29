<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrganizationIndustryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Construction', 'Engineering', 'Water Systems', 'Road Works']).' '.fake()->unique()->numberBetween(100, 999);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'sort_order' => fake()->numberBetween(1, 50),
            'is_active' => true,
        ];
    }
}
