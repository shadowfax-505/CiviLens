<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * @return array<string, string>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::headline($name),
            'slug' => Str::slug($name, '.'),
            'description' => fake()->sentence(),
        ];
    }
}

