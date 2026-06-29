<?php

use App\Models\Award;
use App\Models\BidSubmission;
use App\Models\Contract;
use App\Models\ProcurementActivity;
use App\Models\Tender;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('connects tenders to bids awards contracts and immutable activities', function (): void {
    $tender = Tender::factory()->create();
    $bid = BidSubmission::factory()->create(['tender_id' => $tender->id]);
    $award = Award::factory()->create([
        'tender_id' => $tender->id,
        'bid_submission_id' => $bid->id,
    ]);
    $contract = Contract::factory()->create([
        'award_id' => $award->id,
        'bid_submission_id' => $bid->id,
        'project_id' => $tender->project_id,
        'budget_id' => $tender->budget_id,
    ]);
    $activity = ProcurementActivity::factory()->create([
        'tender_id' => $tender->id,
        'contract_id' => $contract->id,
        'event' => 'contract.created',
    ]);

    expect($tender->bidSubmissions)->toHaveCount(1)
        ->and($tender->awards)->toHaveCount(1)
        ->and($award->contract->is($contract))->toBeTrue()
        ->and($contract->tender->is($tender))->toBeTrue()
        ->and($tender->activities->first()->is($activity))->toBeTrue()
        ->and($activity->delete())->toBeFalse();
});
