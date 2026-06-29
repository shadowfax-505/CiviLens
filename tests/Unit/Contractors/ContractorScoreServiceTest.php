<?php

use App\Models\ComplianceRecord;
use App\Models\ComplianceStatus;
use App\Models\ComplianceType;
use App\Models\ContractorPerformanceSnapshot;
use App\Models\ContractorProfile;
use App\Services\Contractors\ContractorScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('calculates contractor intelligence scores from compliance and performance history', function (): void {
    $profile = ContractorProfile::factory()->create();
    $compliant = ComplianceStatus::factory()->create(['slug' => 'compliant']);
    $failed = ComplianceStatus::factory()->create(['slug' => 'failed']);
    $type = ComplianceType::factory()->create();

    ComplianceRecord::factory()->for($profile)->for($type, 'type')->for($compliant, 'status')->create();
    ComplianceRecord::factory()->for($profile)->for($type, 'type')->for($failed, 'status')->create();

    ContractorPerformanceSnapshot::factory()->for($profile)->create([
        'delay_days' => 10,
        'cost_variance' => 5,
        'quality_rating' => 80,
        'agency_evaluation' => 90,
        'completion_status' => 'completed',
    ]);

    $scores = app(ContractorScoreService::class)->calculate($profile);

    expect($scores['compliance_score'])->toBe(50.0)
        ->and($scores['delivery_score'])->toBe(90.0)
        ->and($scores['financial_score'])->toBe(95.0)
        ->and($scores['quality_score'])->toBe(85.0)
        ->and($scores['contract_success_rate'])->toBe(100.0)
        ->and($scores['average_delay'])->toBe(10.0)
        ->and($scores['average_budget_variance'])->toBe(5.0)
        ->and($scores['overall_contractor_score'])->toBeGreaterThan(70);
});
