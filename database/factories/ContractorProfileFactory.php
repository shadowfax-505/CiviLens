<?php

namespace Database\Factories;

use App\Models\ContractorCategory;
use App\Models\ContractorClassification;
use App\Models\ContractorRegistrationStatus;
use App\Models\ContractorRiskLevel;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractorProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'contractor_category_id' => ContractorCategory::factory(),
            'contractor_classification_id' => ContractorClassification::factory(),
            'contractor_registration_status_id' => ContractorRegistrationStatus::factory(),
            'contractor_risk_level_id' => ContractorRiskLevel::factory(),
            'is_active' => true,
            'is_suspended' => false,
            'is_blacklisted' => false,
            'is_public' => true,
        ];
    }
}
