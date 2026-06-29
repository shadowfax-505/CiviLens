<?php

namespace Database\Factories;

use App\Models\BidSubmission;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationScore;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationScore>
 */
class EvaluationScoreFactory extends Factory
{
    public function definition(): array
    {
        return [
            'bid_submission_id' => BidSubmission::factory(),
            'evaluation_criterion_id' => EvaluationCriterion::factory(),
            'committee_member_id' => null,
            'score' => fake()->randomFloat(2, 50, 100),
            'comments' => fake()->sentence(),
        ];
    }
}
