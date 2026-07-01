<?php

namespace Database\Factories;

use App\Models\IntelligenceEvidence;
use App\Models\IntelligenceIndicator;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class IntelligenceEvidenceFactory extends Factory
{
    protected $model = IntelligenceEvidence::class;

    public function definition(): array
    {
        return [
            'intelligence_indicator_id' => IntelligenceIndicator::factory(),
            'evidenceable_type' => Project::class,
            'evidenceable_id' => Project::factory(),
            'label' => fake()->words(3, true),
            'summary' => fake()->sentence(),
            'weight' => fake()->numberBetween(20, 100),
            'payload' => ['source' => 'factory'],
            'created_by' => User::factory(),
        ];
    }
}
