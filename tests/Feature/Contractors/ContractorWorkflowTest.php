<?php

use App\Events\ContractorRegistered;
use App\Events\PerformanceSnapshotCreated;
use App\Jobs\RecalculateContractorRiskScore;
use App\Models\BlacklistHistory;
use App\Models\Contract;
use App\Models\ContractorActivity;
use App\Models\ContractorCategory;
use App\Models\ContractorClassification;
use App\Models\ContractorProfile;
use App\Models\ContractorRegistrationStatus;
use App\Models\ContractorRiskLevel;
use App\Models\Organization;
use App\Services\Contractors\ContractorLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('registers contractors with traceable activities events and queued risk calculation', function (): void {
    Event::fake([ContractorRegistered::class]);
    Queue::fake();

    $organization = Organization::factory()->create();
    $categoryId = ContractorCategory::factory()->create()->id;
    $classificationId = ContractorClassification::factory()->create()->id;
    $registrationStatusId = ContractorRegistrationStatus::factory()->create(['slug' => 'registered'])->id;
    $riskLevelId = ContractorRiskLevel::factory()->create(['slug' => 'medium'])->id;

    $profile = app(ContractorLifecycleService::class)->registerProfile($organization, [
        'contractor_category_id' => $categoryId,
        'contractor_classification_id' => $classificationId,
        'contractor_registration_status_id' => $registrationStatusId,
        'contractor_risk_level_id' => $riskLevelId,
        'is_active' => true,
        'is_suspended' => false,
        'is_blacklisted' => false,
        'is_public' => true,
    ]);

    expect($profile)->toBeInstanceOf(ContractorProfile::class)
        ->and(ContractorActivity::query()->where('contractor_profile_id', $profile->id)->where('event', 'contractor.registered')->exists())->toBeTrue();

    Event::assertDispatched(ContractorRegistered::class);
    Queue::assertPushed(RecalculateContractorRiskScore::class);
});

it('keeps blacklist history immutable', function (): void {
    $history = BlacklistHistory::factory()->create();

    expect($history->delete())->toBeFalse()
        ->and(BlacklistHistory::query()->whereKey($history->id)->exists())->toBeTrue();
});

it('creates immutable performance snapshots for completed contracts', function (): void {
    Event::fake([PerformanceSnapshotCreated::class]);

    $profile = ContractorProfile::factory()->create();
    $contract = Contract::factory()->create(['status' => 'completed']);

    $snapshot = app(ContractorLifecycleService::class)->createPerformanceSnapshot($profile, $contract, [
        'planned_completion_date' => '2026-06-30',
        'actual_completion_date' => '2026-07-10',
        'final_cost' => 1050000,
        'quality_rating' => 82,
        'agency_evaluation' => 88,
        'completion_status' => 'completed',
    ]);

    expect($snapshot->delay_days)->toBe(10)
        ->and($snapshot->project_id)->toBe($contract->project_id)
        ->and($snapshot->agency_id)->toBe($contract->project->agency_id)
        ->and($snapshot->budget_id)->toBe($contract->budget_id)
        ->and($snapshot->delete())->toBeFalse();

    Event::assertDispatched(PerformanceSnapshotCreated::class);
});
