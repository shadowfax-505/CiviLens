<?php

namespace Database\Factories;

use App\Models\AnalyticsSnapshotPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

class AnalyticsSnapshotPeriodFactory extends Factory
{
    protected $model = AnalyticsSnapshotPeriod::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'slug' => fake()->unique()->slug(),
            'sort_order' => fake()->numberBetween(1, 99),
            'is_active' => true,
        ];
    }
}
