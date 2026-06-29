<?php

namespace Database\Factories;

use App\Models\Tender;
use App\Models\TenderLot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenderLot>
 */
class TenderLotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tender_id' => Tender::factory(),
            'lot_number' => 'LOT-'.fake()->unique()->numberBetween(1, 999),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}
