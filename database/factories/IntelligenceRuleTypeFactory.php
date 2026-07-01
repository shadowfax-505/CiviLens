<?php

namespace Database\Factories;

use App\Models\IntelligenceRuleType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class IntelligenceRuleTypeFactory extends Factory
{
    protected $model = IntelligenceRuleType::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => Str::headline($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'sort_order' => fake()->numberBetween(1, 100),
            'is_active' => true,
        ];
    }
}
