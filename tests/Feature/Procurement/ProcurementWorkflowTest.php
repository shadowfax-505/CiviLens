<?php

use App\Models\Award;
use App\Models\BidderOrganization;
use App\Models\BidSubmission;
use App\Models\Budget;
use App\Models\Contract;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationScore;
use App\Models\ProcurementActivity;
use App\Models\ProcurementMethod;
use App\Models\Role;
use App\Models\Tender;
use App\Models\TenderCategory;
use App\Models\TenderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function procurementAdmin(): User
{
    $role = Role::query()->create(['name' => 'Administrator', 'slug' => config('civiclens.roles.admin')]);
    $user = User::factory()->create();
    $user->roles()->attach($role);

    return $user;
}

function tenderPayload(array $overrides = []): array
{
    $budget = Budget::factory()->create(['current_allocation' => 1500000]);
    $method = ProcurementMethod::factory()->create(['name' => 'Open Tendering', 'slug' => 'open-tendering']);
    $category = TenderCategory::factory()->create(['name' => 'Works', 'slug' => 'works']);
    $status = TenderStatus::factory()->create(['name' => 'Draft', 'slug' => 'draft']);

    return array_merge([
        'project_id' => $budget->project_id,
        'budget_id' => $budget->id,
        'agency_id' => $budget->project->agency_id,
        'procurement_method_id' => $method->id,
        'tender_category_id' => $category->id,
        'tender_status_id' => $status->id,
        'tender_number' => 'TDR-2099-001',
        'title' => 'Riverside Bridge Works Tender',
        'slug' => 'riverside-bridge-works-tender',
        'description' => 'Procurement package for bridge civil works.',
        'published_at' => null,
        'closing_at' => '2099-08-30 17:00:00',
        'is_public' => '1',
        'is_active' => '1',
    ], $overrides);
}

it('restricts procurement management to authorized administrators', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/procurement/tenders')->assertForbidden();
    $this->actingAs($user)->post('/admin/procurement/tenders', [])->assertForbidden();
});

it('lets administrators manage tender lifecycle and immutable timeline activity', function (): void {
    $admin = procurementAdmin();
    $payload = tenderPayload();

    $this->actingAs($admin)->post('/admin/procurement/tenders', $payload)->assertRedirect();

    $tender = Tender::query()->where('tender_number', 'TDR-2099-001')->firstOrFail();

    $this->actingAs($admin)->get("/admin/procurement/tenders/{$tender->id}")
        ->assertOk()
        ->assertSee('Riverside Bridge Works Tender')
        ->assertSee('Procurement Timeline');

    $this->actingAs($admin)->put("/admin/procurement/tenders/{$tender->id}", array_merge($payload, [
        'title' => 'Updated Bridge Works Tender',
    ]))->assertRedirect();

    $this->actingAs($admin)->patch("/admin/procurement/tenders/{$tender->id}/publish")->assertRedirect();
    expect($tender->fresh()->published_at)->not->toBeNull();

    $this->actingAs($admin)->patch("/admin/procurement/tenders/{$tender->id}/close")->assertRedirect();
    expect($tender->fresh()->closed_at)->not->toBeNull();

    $this->actingAs($admin)->patch("/admin/procurement/tenders/{$tender->id}/archive")->assertRedirect();
    expect($tender->fresh()->archived_at)->not->toBeNull();

    $this->actingAs($admin)->patch("/admin/procurement/tenders/{$tender->id}/restore")->assertRedirect();
    expect($tender->fresh()->archived_at)->toBeNull();

    expect(ProcurementActivity::query()->where('tender_id', $tender->id)->pluck('event')->all())
        ->toContain('tender.created', 'tender.updated', 'tender.published', 'tender.closed', 'tender.archived', 'tender.restored');

    $activity = ProcurementActivity::query()->where('tender_id', $tender->id)->firstOrFail();
    expect($activity->delete())->toBeFalse()
        ->and(ProcurementActivity::query()->whereKey($activity->id)->exists())->toBeTrue();
});

it('manages bids evaluation awards and contracts without copying budget allocations', function (): void {
    $admin = procurementAdmin();
    $tender = Tender::factory()->create();
    $bidder = BidderOrganization::factory()->create(['name' => 'Delta Builders Ltd', 'slug' => 'delta-builders-ltd']);

    $this->actingAs($admin)->post("/admin/procurement/tenders/{$tender->id}/bids", [
        'bidder_organization_id' => $bidder->id,
        'reference_number' => 'BID-DELTA-001',
        'submitted_at' => '2099-07-15 10:00:00',
        'technical_score' => '82.50',
        'financial_score' => '89.00',
        'status' => 'submitted',
        'notes' => 'Responsive bid.',
    ])->assertRedirect();

    $bid = BidSubmission::query()->where('reference_number', 'BID-DELTA-001')->firstOrFail();

    $this->actingAs($admin)->post("/admin/procurement/tenders/{$tender->id}/criteria", [
        'name' => 'Technical Methodology',
        'description' => 'Quality of delivery plan.',
        'max_score' => '100',
        'weight' => '60',
        'sort_order' => 1,
    ])->assertRedirect();

    $criterion = EvaluationCriterion::query()->where('tender_id', $tender->id)->firstOrFail();

    $this->actingAs($admin)->post("/admin/procurement/bid-submissions/{$bid->id}/scores", [
        'evaluation_criterion_id' => $criterion->id,
        'score' => '88.50',
        'comments' => 'Strong methodology.',
    ])->assertRedirect();

    expect(EvaluationScore::query()->where('bid_submission_id', $bid->id)->exists())->toBeTrue();

    $this->actingAs($admin)->post("/admin/procurement/tenders/{$tender->id}/awards", [
        'bid_submission_id' => $bid->id,
        'awarded_at' => '2099-07-30',
        'status' => 'approved',
        'notes' => 'Best evaluated bidder.',
    ])->assertRedirect();

    $award = Award::query()->where('tender_id', $tender->id)->firstOrFail();

    $this->actingAs($admin)->post("/admin/procurement/awards/{$award->id}/contracts", [
        'contract_number' => 'CTR-2099-001',
        'title' => 'Bridge Construction Contract',
        'status' => 'active',
        'signed_at' => '2099-08-01',
        'start_date' => '2099-08-05',
        'end_date' => '2100-02-28',
        'notes' => 'Contract references project budget only.',
    ])->assertRedirect();

    $contract = Contract::query()->where('contract_number', 'CTR-2099-001')->firstOrFail();

    $this->actingAs($admin)->get("/admin/procurement/contracts/{$contract->id}")
        ->assertOk()
        ->assertSee('Bridge Construction Contract')
        ->assertSee('Delta Builders Ltd');

    expect($contract->budget_id)->toBe($tender->budget_id)
        ->and($contract->project_id)->toBe($tender->project_id);
});

it('supports tender search filtering sorting pagination and finance-backed budget filters', function (): void {
    $admin = procurementAdmin();
    $payload = tenderPayload();
    Tender::factory()->create([
        'project_id' => $payload['project_id'],
        'budget_id' => $payload['budget_id'],
        'agency_id' => $payload['agency_id'],
        'procurement_method_id' => $payload['procurement_method_id'],
        'tender_category_id' => $payload['tender_category_id'],
        'tender_status_id' => $payload['tender_status_id'],
        'tender_number' => 'TDR-BRIDGE-555',
        'title' => 'Bridge Expansion Procurement',
        'closing_at' => '2099-08-30 17:00:00',
    ]);
    Tender::factory()->create(['tender_number' => 'TDR-WATER-999', 'title' => 'Water Plant Procurement']);

    $query = http_build_query([
        'search' => 'Bridge',
        'project_id' => $payload['project_id'],
        'agency_id' => $payload['agency_id'],
        'procurement_method_id' => $payload['procurement_method_id'],
        'tender_status_id' => $payload['tender_status_id'],
        'budget_min' => 1000000,
        'budget_max' => 2000000,
        'sort' => 'tender_number',
        'direction' => 'asc',
    ]);

    $this->actingAs($admin)->get('/admin/procurement/tenders?'.$query)
        ->assertOk()
        ->assertSee('TDR-BRIDGE-555')
        ->assertDontSee('TDR-WATER-999');
});

it('validates tender relationships and date windows', function (): void {
    $admin = procurementAdmin();

    $this->actingAs($admin)->post('/admin/procurement/tenders', [
        'project_id' => 999,
        'budget_id' => 999,
        'agency_id' => 999,
        'procurement_method_id' => 999,
        'tender_category_id' => 999,
        'tender_status_id' => 999,
        'tender_number' => '',
        'title' => '',
        'slug' => '',
        'published_at' => '2099-09-01 09:00:00',
        'closing_at' => '2099-08-01 09:00:00',
    ])->assertSessionHasErrors([
        'project_id',
        'budget_id',
        'agency_id',
        'procurement_method_id',
        'tender_category_id',
        'tender_status_id',
        'tender_number',
        'title',
        'slug',
        'closing_at',
    ]);
});
