<?php

namespace Database\Factories;

use App\Models\EvaluationCriterion;
use App\Models\Tender;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationCriterion>
 */
class EvaluationCriterionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tender_id' => Tender::factory(),
            'name' => fake()->sentence(2),
            'description' => fake()->sentence(),
            'max_score' => 100,
            'weight' => fake()->randomElement([20, 40, 60]),
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}
