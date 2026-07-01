<?php

namespace Database\Factories;

use App\Models\AnalyticsAlertRule;
use Illuminate\Database\Eloquent\Factories\Factory;

class AnalyticsAlertRuleFactory extends Factory
{
    protected $model = AnalyticsAlertRule::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->sentence(3),
            'slug' => fake()->unique()->slug(),
            'category' => 'finance',
            'metric_key' => 'finance.budget_utilization',
            'operator' => '>=',
            'threshold' => 90,
            'severity' => 'warning',
            'message_template' => ':metric reached :value.',
            'is_active' => true,
            'sort_order' => 10,
        ];
    }
}
