<?php

namespace Database\Factories;

use App\Models\EvaluationCommittee;
use App\Models\Tender;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationCommittee>
 */
class EvaluationCommitteeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tender_id' => Tender::factory(),
            'name' => fake()->sentence(3),
            'formed_at' => now()->toDateString(),
            'status' => 'active',
        ];
    }
}
