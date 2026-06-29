<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\BudgetTransaction;
use App\Models\BudgetTransactionType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetTransaction>
 */
class BudgetTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'budget_id' => Budget::factory(),
            'budget_transaction_type_id' => BudgetTransactionType::factory(),
            'user_id' => User::factory(),
            'amount' => fake()->randomFloat(2, 1000, 100000),
            'transaction_date' => fake()->date(),
            'description' => fake()->sentence(),
        ];
    }
}
