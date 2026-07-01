<?php

namespace Database\Factories;

use App\Models\IntelligenceActivity;
use App\Models\IntelligenceIndicator;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class IntelligenceActivityFactory extends Factory
{
    protected $model = IntelligenceActivity::class;

    public function definition(): array
    {
        return [
            'intelligence_indicator_id' => IntelligenceIndicator::factory(),
            'intelligence_rule_id' => null,
            'intelligence_processing_job_id' => null,
            'actor_id' => User::factory(),
            'event' => fake()->randomElement(['indicator_detected', 'indicator_reviewed', 'job_queued']),
            'description' => fake()->sentence(),
            'properties' => [],
            'occurred_at' => now(),
        ];
    }
}
