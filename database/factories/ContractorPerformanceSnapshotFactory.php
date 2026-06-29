<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\Contract;
use App\Models\ContractorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractorPerformanceSnapshotFactory extends Factory
{
    public function definition(): array
    {
        $budget = Budget::factory()->create();

        return [
            'contractor_profile_id' => ContractorProfile::factory(),
            'project_id' => $budget->project_id,
            'contract_id' => Contract::factory(),
            'agency_id' => $budget->project->agency_id,
            'budget_id' => $budget->id,
            'planned_completion_date' => now()->subMonth()->toDateString(),
            'actual_completion_date' => now()->subDays(20)->toDateString(),
            'delay_days' => 10,
            'final_cost' => 1000000,
            'cost_variance' => 0,
            'quality_rating' => 80,
            'agency_evaluation' => 80,
            'completion_status' => 'completed',
        ];
    }
}
