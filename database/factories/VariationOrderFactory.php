<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\VariationOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VariationOrder>
 */
class VariationOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'approved_at' => null,
            'status' => 'pending',
        ];
    }
}
