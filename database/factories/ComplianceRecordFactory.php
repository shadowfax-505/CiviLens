<?php

namespace Database\Factories;

use App\Models\ComplianceStatus;
use App\Models\ComplianceType;
use App\Models\ContractorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class ComplianceRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'contractor_profile_id' => ContractorProfile::factory(),
            'compliance_type_id' => ComplianceType::factory(),
            'compliance_status_id' => ComplianceStatus::factory(),
            'findings' => fake()->sentence(),
            'inspection_date' => now()->subMonth()->toDateString(),
            'next_review_date' => now()->addMonths(6)->toDateString(),
        ];
    }
}
