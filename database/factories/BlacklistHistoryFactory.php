<?php

namespace Database\Factories;

use App\Models\ContractorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class BlacklistHistoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'contractor_profile_id' => ContractorProfile::factory(),
            'started_on' => now()->subMonth()->toDateString(),
            'ended_on' => null,
            'reason' => 'Material compliance violation.',
        ];
    }
}
