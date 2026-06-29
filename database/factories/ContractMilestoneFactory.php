<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractMilestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractMilestone>
 */
class ContractMilestoneFactory extends Factory
{
    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'title' => fake()->sentence(3),
            'due_date' => now()->addMonth()->toDateString(),
            'completed_at' => null,
            'status' => 'pending',
            'notes' => fake()->sentence(),
        ];
    }
}
