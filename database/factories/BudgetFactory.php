<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\BudgetCategory;
use App\Models\BudgetStatus;
use App\Models\BudgetType;
use App\Models\FiscalYear;
use App\Models\FundingSource;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    public function definition(): array
    {
        $allocation = fake()->randomFloat(2, 100000, 10000000);

        return [
            'project_id' => Project::factory(),
            'fiscal_year_id' => FiscalYear::factory(),
            'budget_type_id' => BudgetType::factory(),
            'funding_source_id' => FundingSource::factory(),
            'budget_category_id' => BudgetCategory::factory(),
            'budget_status_id' => BudgetStatus::factory(),
            'original_allocation' => $allocation,
            'current_allocation' => $allocation,
            'reserved_amount' => fake()->randomFloat(2, 0, 50000),
            'committed_amount' => fake()->randomFloat(2, 0, 100000),
            'actual_expenditure' => fake()->randomFloat(2, 0, 100000),
            'currency' => 'BDT',
            'notes' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
