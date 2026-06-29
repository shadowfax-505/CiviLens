<?php

namespace Database\Factories;

use App\Models\BudgetTransactionType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BudgetTransactionType>
 */
class BudgetTransactionTypeFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::headline($name),
            'slug' => Str::slug($name),
            'direction' => fake()->randomElement(['increase', 'decrease', 'neutral']),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
