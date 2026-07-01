<?php

namespace Database\Factories;

use App\Models\DashboardState;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DashboardStateFactory extends Factory
{
    protected $model = DashboardState::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'dashboard' => 'executive',
            'filters' => [],
            'is_default' => false,
        ];
    }
}
