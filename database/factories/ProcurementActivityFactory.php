<?php

namespace Database\Factories;

use App\Models\ProcurementActivity;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcurementActivity>
 */
class ProcurementActivityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tender_id' => Tender::factory(),
            'contract_id' => null,
            'actor_id' => User::factory(),
            'event' => 'tender.created',
            'description' => fake()->sentence(),
            'old_values' => null,
            'new_values' => ['status' => 'created'],
        ];
    }
}
