<?php

use App\Models\ComplianceStatus;
use App\Models\ComplianceType;
use App\Models\ContractorActivityType;
use App\Models\ContractorCategory;
use App\Models\ContractorClassification;
use App\Models\ContractorRegistrationStatus;
use App\Models\ContractorRiskLevel;
use App\Models\OrganizationCompanyType;
use App\Models\OrganizationIndustry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds contractor lookup data for normalized profiles and activities', function (): void {
    $this->seed();

    expect(OrganizationCompanyType::query()->where('slug', 'limited-company')->exists())->toBeTrue()
        ->and(OrganizationIndustry::query()->where('slug', 'construction')->exists())->toBeTrue()
        ->and(ContractorCategory::query()->where('slug', 'civil-works')->exists())->toBeTrue()
        ->and(ContractorClassification::query()->where('slug', 'class-a')->exists())->toBeTrue()
        ->and(ContractorRegistrationStatus::query()->where('slug', 'registered')->exists())->toBeTrue()
        ->and(ContractorRiskLevel::query()->where('slug', 'medium')->exists())->toBeTrue()
        ->and(ComplianceType::query()->where('slug', 'tax-compliance')->exists())->toBeTrue()
        ->and(ComplianceStatus::query()->where('slug', 'compliant')->exists())->toBeTrue()
        ->and(ContractorActivityType::query()->where('slug', 'registered')->exists())->toBeTrue();
});
