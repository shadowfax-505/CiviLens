<?php

namespace Database\Factories;

use App\Models\IntelligenceProcessingJob;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class IntelligenceProcessingJobFactory extends Factory
{
    protected $model = IntelligenceProcessingJob::class;

    public function definition(): array
    {
        return [
            'target_type' => Project::class,
            'target_id' => Project::factory(),
            'job_type' => fake()->randomElement(['ocr_preparation', 'ai_review_preparation', 'search_sync']),
            'status' => 'queued',
            'attempts' => 0,
            'payload' => [],
            'error_summary' => null,
            'queued_by' => User::factory(),
            'queued_at' => now(),
            'started_at' => null,
            'completed_at' => null,
            'failed_at' => null,
        ];
    }
}
