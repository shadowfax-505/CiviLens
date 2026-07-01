<?php

namespace Database\Factories;

use App\Models\IntelligenceIndicator;
use App\Models\IntelligenceRule;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class IntelligenceIndicatorFactory extends Factory
{
    protected $model = IntelligenceIndicator::class;

    public function definition(): array
    {
        return [
            'intelligence_rule_id' => IntelligenceRule::factory(),
            'source_type' => Project::class,
            'source_id' => Project::factory(),
            'module' => 'projects',
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(),
            'severity' => fake()->randomElement(['info', 'warning', 'critical']),
            'confidence_score' => fake()->numberBetween(30, 95),
            'status' => 'pending',
            'detected_at' => now(),
            'rule_version' => '1.0.0',
            'detection_payload' => ['value' => fake()->numberBetween(1, 100)],
            'metadata' => [],
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
