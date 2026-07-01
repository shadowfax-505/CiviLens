<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\ProcurementMethod;
use App\Models\ProcurementPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcurementPlan>
 */
class ProcurementPlanFactory extends Factory
{
    public function definition(): array
    {
        $budget = Budget::factory()->create();

        return [
            'plan_number' => 'PLAN-'.fake()->unique()->numberBetween(1000, 999999),
            'title' => fake()->sentence(4),
            'agency_id' => $budget->project->agency_id,
            'budget_id' => $budget->id,
            'project_id' => $budget->project_id,
            'fiscal_year_id' => $budget->fiscal_year_id,
            'funding_source_id' => $budget->funding_source_id,
            'procurement_method_id' => ProcurementMethod::factory(),
            'estimated_value' => fake()->randomFloat(2, 100000, 5000000),
            'priority' => 'normal',
            'planned_start_date' => now()->addMonth()->toDateString(),
            'planned_award_date' => now()->addMonths(2)->toDateString(),
            'planned_completion_date' => now()->addYear()->toDateString(),
            'status' => 'draft',
        ];
    }
}
