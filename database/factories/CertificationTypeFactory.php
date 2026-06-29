<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CertificationTypeFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Certification '.fake()->unique()->numberBetween(100, 999);

        return ['name' => $name, 'slug' => Str::slug($name), 'is_active' => true];
    }
}
