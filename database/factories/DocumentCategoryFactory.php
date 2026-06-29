<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DocumentCategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Document Category '.fake()->unique()->numberBetween(100, 999);

        return ['name' => $name, 'slug' => Str::slug($name), 'description' => fake()->sentence(), 'is_active' => true];
    }
}
