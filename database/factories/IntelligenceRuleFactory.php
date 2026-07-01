<?php

namespace Database\Factories;

use App\Models\IntelligenceRule;
use App\Models\IntelligenceRuleType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class IntelligenceRuleFactory extends Factory
{
    protected $model = IntelligenceRule::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(4, true);

        return [
            'intelligence_rule_type_id' => IntelligenceRuleType::factory(),
            'name' => Str::headline($name),
            'slug' => Str::slug($name),
            'module' => fake()->randomElement(['projects', 'finance', 'procurement', 'contractors', 'documents', 'search', 'analytics']),
            'category' => fake()->randomElement(['delay', 'budget', 'compliance', 'metadata', 'indexing']),
            'severity_default' => fake()->randomElement(['info', 'warning', 'critical']),
            'thresholds' => ['warning' => 50, 'critical' => 90],
            'configuration' => [],
            'version' => '1.0.0',
            'is_active' => true,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
