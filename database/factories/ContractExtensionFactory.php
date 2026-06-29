<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractExtension;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractExtension>
 */
class ContractExtensionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'previous_end_date' => now()->addMonths(6)->toDateString(),
            'new_end_date' => now()->addMonths(8)->toDateString(),
            'reason' => fake()->sentence(),
            'approved_at' => null,
        ];
    }
}
