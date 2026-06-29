<?php

namespace Database\Factories;

use App\Models\Award;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    public function definition(): array
    {
        $award = Award::factory()->create();
        $tender = $award->tender;

        return [
            'award_id' => $award->id,
            'bid_submission_id' => $award->bid_submission_id,
            'project_id' => $tender->project_id,
            'budget_id' => $tender->budget_id,
            'contract_number' => 'CTR-'.fake()->unique()->numberBetween(1000, 999999),
            'title' => fake()->sentence(4),
            'status' => 'draft',
            'signed_at' => now()->toDateString(),
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'notes' => fake()->sentence(),
        ];
    }
}
