<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectActivity>
 */
class ProjectActivityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'actor_id' => User::factory(),
            'event' => fake()->randomElement(['created', 'updated', 'status_changed', 'progress_updated', 'archived', 'restored', 'deleted']),
            'old_values' => null,
            'new_values' => ['note' => fake()->sentence()],
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
