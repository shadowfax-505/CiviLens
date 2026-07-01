<?php

namespace Database\Factories;

use App\Models\AnalyticsAlert;
use App\Models\AnalyticsAlertRule;
use Illuminate\Database\Eloquent\Factories\Factory;

class AnalyticsAlertFactory extends Factory
{
    protected $model = AnalyticsAlert::class;

    public function definition(): array
    {
        return [
            'analytics_alert_rule_id' => AnalyticsAlertRule::factory(),
            'title' => fake()->sentence(3),
            'message' => fake()->sentence(),
            'severity' => 'warning',
            'status' => 'open',
            'triggered_value' => fake()->randomFloat(2, 90, 100),
            'context' => [],
            'triggered_at' => now(),
        ];
    }
}
