<?php

use App\Events\AwardApproved;
use App\Events\BidOpened;
use App\Events\ContractClosed;
use App\Events\EvaluationCompleted;
use App\Events\MilestoneCompleted;
use App\Events\VariationApproved;
use App\Models\Award;
use App\Models\BidderOrganization;
use App\Models\BidSubmission;
use App\Models\Budget;
use App\Models\Contract;
use App\Models\ContractMilestone;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationSummary;
use App\Models\Organization;
use App\Models\ProcurementMethod;
use App\Models\ProcurementPlan;
use App\Models\Role;
use App\Models\SearchIndex;
use App\Models\Tender;
use App\Models\User;
use App\Models\VariationOrder;
use App\Services\Procurement\EnterpriseProcurementAnalyticsService;
use App\Services\Procurement\ProcurementLifecycleService;
use App\Services\Procurement\ProcurementPlanningService;
use App\Services\Search\SearchIndexingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function enterpriseProcurementAdmin(): User
{
    $role = Role::query()->firstOrCreate(['slug' => config('civiclens.roles.admin')], ['name' => 'Administrator']);
    $user = User::factory()->create();
    $user->roles()->sync([$role->id]);

    return $user;
}

it('creates procurement plans with approval audit history and search indexing', function (): void {
    Event::fake();

    $admin = enterpriseProcurementAdmin();
    $budget = Budget::factory()->create(['current_allocation' => 2500000]);
    $method = ProcurementMethod::factory()->create(['name' => 'Open Tendering', 'slug' => 'open-tendering']);

    $plan = app(ProcurementPlanningService::class)->create([
        'plan_number' => 'PLAN-2100-001',
        'title' => 'District bridge procurement plan',
        'agency_id' => $budget->project->agency_id,
        'budget_id' => $budget->id,
        'project_id' => $budget->project_id,
        'fiscal_year_id' => $budget->fiscal_year_id,
        'funding_source_id' => $budget->funding_source_id,
        'procurement_method_id' => $method->id,
        'estimated_value' => 2400000,
        'priority' => 'high',
        'planned_start_date' => '2100-01-01',
        'planned_award_date' => '2100-03-01',
        'planned_completion_date' => '2100-12-31',
        'status' => 'draft',
    ], $admin);

    app(ProcurementPlanningService::class)->approve($plan, $admin, 'Approved for tender preparation.');
    app(SearchIndexingService::class)->index($plan->refresh());

    expect($plan->refresh()->status)->toBe('approved')
        ->and($plan->approved_by)->toBe($admin->id)
        ->and($plan->activities()->where('event', 'procurement_plan.approved')->exists())->toBeTrue()
        ->and(SearchIndex::query()->where('searchable_type', ProcurementPlan::class)->where('searchable_id', $plan->id)->exists())->toBeTrue();
});

it('keeps bid amounts private until opening and records bid opening events', function (): void {
    Event::fake();

    $admin = enterpriseProcurementAdmin();
    $tender = Tender::factory()->create(['closing_at' => now()->addDay()]);

    $bid = app(ProcurementLifecycleService::class)->createBid($tender, [
        'bidder_organization_id' => BidderOrganization::factory()->create()->id,
        'reference_number' => 'BID-PRIVATE-001',
        'submitted_at' => now(),
        'bid_amount' => 1875000,
        'bid_valid_until' => now()->addMonths(3)->toDateString(),
        'bid_security_amount' => 50000,
        'technical_proposal_summary' => 'Sealed technical proposal.',
        'financial_proposal_summary' => 'Sealed financial proposal.',
        'status' => 'submitted',
    ], $admin);

    $this->get(route('public.procurement.index'))
        ->assertOk()
        ->assertDontSee('1875000');

    $opened = app(ProcurementLifecycleService::class)->openBid($bid, $admin, 'Opened by committee.');

    expect($opened->status)->toBe('opened')
        ->and($opened->openingRecord)->not->toBeNull()
        ->and($opened->openingRecord->opened_by)->toBe($admin->id);

    Event::assertDispatched(BidOpened::class);
});

it('links bidder submissions to contractor intelligence organizations by registration number', function (): void {
    $admin = enterpriseProcurementAdmin();
    $tender = Tender::factory()->create();
    $contractor = Organization::factory()->create(['registration_number' => 'REG-LINK-2100']);
    $bidder = BidderOrganization::factory()->create([
        'registration_number' => 'REG-LINK-2100',
        'organization_id' => null,
    ]);

    app(ProcurementLifecycleService::class)->createBid($tender, [
        'bidder_organization_id' => $bidder->id,
        'reference_number' => 'BID-LINK-001',
        'submitted_at' => now(),
        'status' => 'submitted',
    ], $admin);

    expect($bidder->refresh()->organization_id)->toBe($contractor->id)
        ->and($bidder->organization)->toBeInstanceOf(Organization::class);
});

it('finalizes weighted evaluations and blocks score mutation after completion', function (): void {
    Event::fake();

    $admin = enterpriseProcurementAdmin();
    $bid = BidSubmission::factory()->create(['technical_score' => null, 'financial_score' => null]);
    $technical = EvaluationCriterion::factory()->create(['tender_id' => $bid->tender_id, 'name' => 'Technical', 'max_score' => 100, 'weight' => 70]);
    $financial = EvaluationCriterion::factory()->create(['tender_id' => $bid->tender_id, 'name' => 'Financial', 'max_score' => 100, 'weight' => 30]);

    app(ProcurementLifecycleService::class)->createScore($bid, [
        'evaluation_criterion_id' => $technical->id,
        'score' => 80,
        'comments' => 'Strong.',
    ], $admin);
    app(ProcurementLifecycleService::class)->createScore($bid, [
        'evaluation_criterion_id' => $financial->id,
        'score' => 90,
        'comments' => 'Competitive.',
    ], $admin);

    $summary = app(ProcurementLifecycleService::class)->finalizeEvaluation($bid, $admin, 'Recommended for award.');

    expect($summary)->toBeInstanceOf(EvaluationSummary::class)
        ->and((float) $summary->overall_score)->toBe(83.0)
        ->and($summary->recommendation)->toBe('Recommended for award.');

    expect(fn () => app(ProcurementLifecycleService::class)->createScore($bid, [
        'evaluation_criterion_id' => $technical->id,
        'score' => 85,
    ], $admin))->toThrow(LogicException::class);

    Event::assertDispatched(EvaluationCompleted::class);
});

it('approves awards and manages contract execution closeout with immutable activities', function (): void {
    Event::fake();

    $admin = enterpriseProcurementAdmin();
    $award = Award::factory()->create(['status' => 'recommended']);
    $contract = Contract::factory()->create(['award_id' => $award->id, 'bid_submission_id' => $award->bid_submission_id, 'project_id' => $award->tender->project_id, 'budget_id' => $award->tender->budget_id, 'status' => 'active']);
    $milestone = ContractMilestone::factory()->create(['contract_id' => $contract->id, 'status' => 'pending']);
    $variation = VariationOrder::factory()->create(['contract_id' => $contract->id, 'status' => 'pending']);

    app(ProcurementLifecycleService::class)->approveAward($award, $admin, 'Approved by procurement authority.');
    app(ProcurementLifecycleService::class)->completeMilestone($milestone, $admin, 100, 'Accepted with evidence.');
    app(ProcurementLifecycleService::class)->approveVariation($variation, $admin, [
        'approved_amount' => 125000,
        'schedule_extension_days' => 14,
        'reason' => 'Flood-related scope change.',
    ]);
    app(ProcurementLifecycleService::class)->recordContractPayment($contract, $admin, [
        'payment_reference' => 'PAY-2100-001',
        'amount' => 750000,
        'paid_at' => now()->toDateString(),
        'status' => 'paid',
    ]);
    app(ProcurementLifecycleService::class)->closeContract($contract, $admin, 'Completed and accepted.');

    expect($award->refresh()->status)->toBe('approved')
        ->and($award->approvals()->count())->toBe(1)
        ->and($milestone->refresh()->status)->toBe('completed')
        ->and($variation->refresh()->status)->toBe('approved')
        ->and($contract->refresh()->status)->toBe('closed')
        ->and($contract->payments()->where('payment_reference', 'PAY-2100-001')->exists())->toBeTrue()
        ->and($contract->closeout)->not->toBeNull();

    Event::assertDispatched(AwardApproved::class);
    Event::assertDispatched(MilestoneCompleted::class);
    Event::assertDispatched(VariationApproved::class);
    Event::assertDispatched(ContractClosed::class);
});

it('exposes audited admin workflow endpoints for bid opening evaluation awards and contracts', function (): void {
    $admin = enterpriseProcurementAdmin();
    $this->actingAs($admin);

    $tender = Tender::factory()->create(['closing_at' => now()->addDay()]);
    $bid = app(ProcurementLifecycleService::class)->createBid($tender, [
        'bidder_organization_id' => BidderOrganization::factory()->create()->id,
        'reference_number' => 'BID-ROUTE-001',
        'submitted_at' => now(),
        'bid_amount' => 445000,
        'status' => 'submitted',
    ], $admin);

    $this->post(route('admin.procurement.bid-submissions.open', $bid), ['notes' => 'Opened by committee.'])
        ->assertRedirect();

    $technical = app(ProcurementLifecycleService::class)->createCriterion($tender, [
        'name' => 'Technical score',
        'max_score' => 100,
        'weight' => 60,
    ], $admin);
    $financial = app(ProcurementLifecycleService::class)->createCriterion($tender, [
        'name' => 'Financial score',
        'max_score' => 100,
        'weight' => 40,
    ], $admin);
    app(ProcurementLifecycleService::class)->createScore($bid, [
        'evaluation_criterion_id' => $technical->id,
        'score' => 80,
    ], $admin);
    app(ProcurementLifecycleService::class)->createScore($bid, [
        'evaluation_criterion_id' => $financial->id,
        'score' => 90,
    ], $admin);

    $this->post(route('admin.procurement.bid-submissions.evaluations.finalize', $bid), ['recommendation' => 'Route approved.'])
        ->assertRedirect();

    $award = app(ProcurementLifecycleService::class)->createAward($tender, [
        'bid_submission_id' => $bid->id,
        'status' => 'recommended',
    ], $admin);
    $this->patch(route('admin.procurement.awards.approve', $award), ['notes' => 'Approved by authority.'])
        ->assertRedirect();

    $contract = app(ProcurementLifecycleService::class)->createContract($award->refresh(), [
        'contract_number' => 'CTR-ROUTE-001',
        'title' => 'Route lifecycle contract',
        'status' => 'active',
        'signed_at' => now()->toDateString(),
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
    ], $admin);
    $milestone = ContractMilestone::factory()->create(['contract_id' => $contract->id]);
    $variation = VariationOrder::factory()->create(['contract_id' => $contract->id]);

    $this->post(route('admin.procurement.contracts.payments.store', $contract), [
        'payment_reference' => 'PAY-ROUTE-001',
        'amount' => 10000,
        'status' => 'certified',
        'paid_at' => now()->toDateString(),
        'notes' => 'Mobilization payment.',
    ])->assertRedirect();
    $this->patch(route('admin.procurement.milestones.complete', $milestone), [
        'completion_percentage' => 100,
        'evidence_summary' => 'Evidence accepted.',
    ])->assertRedirect();
    $this->patch(route('admin.procurement.variation-orders.approve', $variation), [
        'approved_amount' => 5000,
        'schedule_extension_days' => 7,
        'reason' => 'Approved scope adjustment.',
    ])->assertRedirect();
    $this->patch(route('admin.procurement.contracts.close', $contract), ['notes' => 'Closed after acceptance.'])
        ->assertRedirect();

    expect($bid->refresh()->status)->toBe('evaluated')
        ->and($award->refresh()->status)->toBe('approved')
        ->and($contract->refresh()->status)->toBe('closed')
        ->and($contract->payments()->count())->toBe(1)
        ->and($milestone->refresh()->status)->toBe('completed')
        ->and($variation->refresh()->status)->toBe('approved');
});

it('calculates enterprise procurement intelligence metrics', function (): void {
    $tender = Tender::factory()->create(['published_at' => now()->subDays(20), 'closed_at' => now()->subDays(10)]);
    BidSubmission::factory()->count(1)->create(['tender_id' => $tender->id, 'status' => 'opened']);
    Award::factory()->create(['tender_id' => $tender->id, 'bid_submission_id' => BidSubmission::query()->where('tender_id', $tender->id)->value('id'), 'status' => 'approved']);
    $contract = Contract::factory()->create(['status' => 'closed']);
    VariationOrder::factory()->create(['contract_id' => $contract->id, 'status' => 'approved']);

    $metrics = app(EnterpriseProcurementAnalyticsService::class)->metrics();

    expect($metrics['single_bid_tenders'])->toBeGreaterThanOrEqual(1)
        ->and($metrics['average_bidders'])->toBeGreaterThanOrEqual(1.0)
        ->and($metrics['variation_frequency'])->toBeGreaterThanOrEqual(1.0)
        ->and($metrics)->toHaveKeys([
            'tender_participation',
            'award_distribution',
            'repeat_winners',
            'procurement_duration_days',
            'contract_completion_rate',
        ]);
});
