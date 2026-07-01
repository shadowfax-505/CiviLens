<?php

namespace Database\Factories;

use App\Models\IntelligenceIndicator;
use App\Models\IntelligenceReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class IntelligenceReviewFactory extends Factory
{
    protected $model = IntelligenceReview::class;

    public function definition(): array
    {
        return [
            'intelligence_indicator_id' => IntelligenceIndicator::factory(),
            'reviewed_by' => User::factory(),
            'status' => fake()->randomElement(['in_review', 'accepted', 'dismissed', 'needs_more_evidence']),
            'notes' => fake()->sentence(),
            'reviewed_at' => now(),
            'follow_up_on' => fake()->optional()->date(),
        ];
    }
}
