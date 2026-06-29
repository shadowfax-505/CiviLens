<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\LiquidatedDamage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiquidatedDamage>
 */
class LiquidatedDamageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'reason' => fake()->sentence(),
            'assessed_at' => now()->toDateString(),
            'days_delayed' => fake()->numberBetween(1, 60),
            'assessed_amount' => fake()->randomFloat(2, 1000, 100000),
        ];
    }
}
