<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\BudgetRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetRevision>
 */
class BudgetRevisionFactory extends Factory
{
    public function definition(): array
    {
        $previous = fake()->randomFloat(2, 100000, 1000000);
        $new = fake()->randomFloat(2, 1000000, 2000000);

        return [
            'budget_id' => Budget::factory(),
            'revision_number' => fake()->unique()->numberBetween(1, 9999),
            'previous_allocation' => $previous,
            'new_allocation' => $new,
            'difference' => $new - $previous,
            'reason' => fake()->sentence(),
            'approval_date' => fake()->date(),
            'approved_by' => User::factory(),
        ];
    }
}
