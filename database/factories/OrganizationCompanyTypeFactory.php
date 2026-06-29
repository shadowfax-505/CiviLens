<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrganizationCompanyTypeFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Limited Company', 'Partnership', 'Sole Proprietorship']).' '.fake()->unique()->numberBetween(100, 999);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'sort_order' => fake()->numberBetween(1, 50),
            'is_active' => true,
        ];
    }
}
