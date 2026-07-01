<?php

namespace App\Services\Procurement;

use App\Events\AwardApproved;
use App\Events\BidOpened;
use App\Events\ContractAwarded;
use App\Events\ContractClosed;
use App\Events\EvaluationCompleted;
use App\Events\MilestoneCompleted;
use App\Events\TenderPublished;
use App\Events\VariationApproved;
use App\Models\Award;
use App\Models\BidderOrganization;
use App\Models\BidSubmission;
use App\Models\Contract;
use App\Models\ContractMilestone;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationScore;
use App\Models\EvaluationSummary;
use App\Models\Organization;
use App\Models\Tender;
use App\Models\TenderStatus;
use App\Models\User;
use App\Models\VariationOrder;
use Illuminate\Support\Facades\DB;
use LogicException;

class ProcurementLifecycleService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createTender(array $data, User $actor): Tender
    {
        return DB::transaction(function () use ($data, $actor): Tender {
            $tender = Tender::query()->create($this->normalizeTenderData($data));
            $this->recordTenderActivity($tender, $actor, 'tender.created', 'Tender created.', newValues: $tender->only(['tender_number', 'title']));

            return $tender;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateTender(Tender $tender, array $data, User $actor): Tender
    {
        return DB::transaction(function () use ($tender, $data, $actor): Tender {
            $before = $tender->only(['tender_number', 'title', 'tender_status_id', 'closing_at', 'is_active']);
            $tender->update($this->normalizeTenderData($data));
            $this->recordTenderActivity($tender, $actor, 'tender.updated', 'Tender updated.', $before, $tender->only(array_keys($before)));

            return $tender;
        });
    }

    public function publish(Tender $tender, User $actor): void
    {
        $tender->update([
            'published_at' => $tender->published_at ?? now(),
            'tender_status_id' => $this->statusId('published', $tender->tender_status_id),
        ]);

        $this->recordTenderActivity($tender, $actor, 'tender.published', 'Tender published.');
        TenderPublished::dispatch($tender, $actor);
    }

    public function close(Tender $tender, User $actor): void
    {
        $tender->update([
            'closed_at' => now(),
            'tender_status_id' => $this->statusId('closed', $tender->tender_status_id),
        ]);

        $this->recordTenderActivity($tender, $actor, 'tender.closed', 'Tender closed.');
    }

    public function archive(Tender $tender, User $actor): void
    {
        $tender->update(['archived_at' => now(), 'is_active' => false]);
        $this->recordTenderActivity($tender, $actor, 'tender.archived', 'Tender archived.');
    }

    public function restore(Tender $tender, User $actor): void
    {
        $tender->update(['archived_at' => null, 'is_active' => true]);
        $this->recordTenderActivity($tender, $actor, 'tender.restored', 'Tender restored.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createBid(Tender $tender, array $data, User $actor): BidSubmission
    {
        $bid = BidSubmission::query()->create(array_merge($data, ['tender_id' => $tender->id]));
        $this->linkBidderToContractorOrganization($bid);
        $this->recordTenderActivity($tender, $actor, 'bid.submitted', 'Bid submission recorded.', newValues: ['reference_number' => $bid->reference_number]);

        return $bid;
    }

    public function openBid(BidSubmission $bidSubmission, User $actor, ?string $notes = null): BidSubmission
    {
        return DB::transaction(function () use ($bidSubmission, $actor, $notes): BidSubmission {
            if ($bidSubmission->withdrawn_at !== null) {
                throw new LogicException('Withdrawn bids cannot be opened.');
            }

            $bidSubmission->forceFill([
                'status' => 'opened',
                'opened_at' => now(),
            ])->save();

            $bidSubmission->openingRecord()->create([
                'opened_by' => $actor->id,
                'opened_at' => $bidSubmission->opened_at,
                'recorded_amount' => $bidSubmission->bid_amount,
                'notes' => $notes,
            ]);

            $tender = $bidSubmission->tender;
            if ($tender instanceof Tender) {
                $this->recordTenderActivity($tender, $actor, 'bid.opened', $notes ?? 'Bid opened.', newValues: ['reference_number' => $bidSubmission->reference_number]);
            }

            BidOpened::dispatch($bidSubmission, $actor);

            return $bidSubmission->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createCriterion(Tender $tender, array $data, User $actor): EvaluationCriterion
    {
        $criterion = EvaluationCriterion::query()->create(array_merge($data, [
            'tender_id' => $tender->id,
            'sort_order' => $data['sort_order'] ?? 0,
        ]));
        $this->recordTenderActivity($tender, $actor, 'evaluation.criterion_created', 'Evaluation criterion created.', newValues: ['name' => $criterion->name]);

        return $criterion;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createScore(BidSubmission $bidSubmission, array $data, User $actor): EvaluationScore
    {
        $tender = $bidSubmission->tender;
        if (! $tender instanceof Tender) {
            throw new LogicException('Bid submission must belong to a tender before scoring.');
        }
        if ($bidSubmission->evaluationSummary()->exists()) {
            throw new LogicException('Evaluation scores are immutable after evaluation finalization.');
        }

        $score = EvaluationScore::query()->create(array_merge($data, ['bid_submission_id' => $bidSubmission->id]));
        $this->recordTenderActivity($tender, $actor, 'evaluation.score_recorded', 'Evaluation score recorded.', newValues: ['score' => $score->score]);

        return $score;
    }

    public function finalizeEvaluation(BidSubmission $bidSubmission, User $actor, ?string $recommendation = null): EvaluationSummary
    {
        return DB::transaction(function () use ($bidSubmission, $actor, $recommendation): EvaluationSummary {
            if ($bidSubmission->evaluationSummary()->exists()) {
                throw new LogicException('Evaluation has already been finalized.');
            }

            $scores = EvaluationScore::query()
                ->with('criterion')
                ->where('bid_submission_id', $bidSubmission->id)
                ->get();
            $weighted = 0.0;
            $technical = $bidSubmission->technical_score !== null ? (float) $bidSubmission->technical_score : 0.0;
            $financial = $bidSubmission->financial_score !== null ? (float) $bidSubmission->financial_score : 0.0;
            $scorePayload = [];

            foreach ($scores as $score) {
                if (! $score instanceof EvaluationScore) {
                    continue;
                }

                $criterion = $this->evaluationCriterion($score);
                $weight = $criterion instanceof EvaluationCriterion ? (float) $criterion->weight : 0.0;
                $maxScore = $criterion instanceof EvaluationCriterion ? max((float) $criterion->max_score, 1.0) : 100.0;
                $criterionName = $criterion instanceof EvaluationCriterion ? $criterion->name : null;
                $scoreValue = (float) $score->score;

                $weighted += (($scoreValue / $maxScore) * 100) * ($weight / 100);

                if ($criterionName !== null && str_contains(strtolower($criterionName), 'technical')) {
                    $technical = $scoreValue;
                }

                if ($criterionName !== null && str_contains(strtolower($criterionName), 'financial')) {
                    $financial = $scoreValue;
                }

                $scorePayload[] = [
                    'criterion' => $criterionName,
                    'score' => $scoreValue,
                    'weight' => $weight,
                ];
            }

            $summary = new EvaluationSummary([
                'technical_score' => $technical,
                'financial_score' => $financial,
                'compliance_score' => 100,
                'overall_score' => round($weighted, 2),
                'recommendation' => $recommendation,
                'finalized_by' => $actor->id,
                'finalized_at' => now(),
                'score_payload' => $scorePayload,
            ]);
            $summary->bidSubmission()->associate($bidSubmission);
            $summary->save();

            $bidSubmission->forceFill([
                'technical_score' => $technical,
                'financial_score' => $financial,
                'status' => 'evaluated',
            ])->save();

            $tender = $bidSubmission->tender;
            if ($tender instanceof Tender) {
                $this->recordTenderActivity($tender, $actor, 'evaluation.completed', 'Evaluation finalized.', newValues: ['bid_submission_id' => $bidSubmission->id, 'overall_score' => $summary->overall_score]);
            }

            EvaluationCompleted::dispatch($summary, $actor);

            return $summary;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createAward(Tender $tender, array $data, User $actor): Award
    {
        $award = Award::query()->create(array_merge($data, ['tender_id' => $tender->id]));
        $this->recordTenderActivity($tender, $actor, 'award.created', 'Award decision recorded.', newValues: ['bid_submission_id' => $award->bid_submission_id]);
        ContractAwarded::dispatch($award, $actor);

        return $award;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createContract(Award $award, array $data, User $actor): Contract
    {
        $tender = $award->tender;
        if (! $tender instanceof Tender) {
            throw new LogicException('Award must belong to a tender before contract creation.');
        }

        $contract = Contract::query()->create(array_merge($data, [
            'award_id' => $award->id,
            'bid_submission_id' => $award->bid_submission_id,
            'project_id' => $tender->project_id,
            'budget_id' => $tender->budget_id,
        ]));

        $this->recordTenderActivity($tender, $actor, 'contract.created', 'Contract created.', contract: $contract, newValues: ['contract_number' => $contract->contract_number]);

        return $contract;
    }

    public function approveAward(Award $award, User $actor, ?string $notes = null): Award
    {
        return DB::transaction(function () use ($award, $actor, $notes): Award {
            $award->forceFill([
                'status' => 'approved',
                'awarded_at' => $award->awarded_at ?? now()->toDateString(),
                'public_disclosure_status' => 'public',
            ])->save();

            $award->approvals()->create([
                'approved_by' => $actor->id,
                'status' => 'approved',
                'notes' => $notes,
                'approved_at' => now(),
            ]);

            $tender = $award->tender;
            if ($tender instanceof Tender) {
                $this->recordTenderActivity($tender, $actor, 'award.approved', $notes ?? 'Award approved.', newValues: ['award_id' => $award->id]);
            }

            AwardApproved::dispatch($award, $actor);

            return $award->refresh();
        });
    }

    public function completeMilestone(ContractMilestone $milestone, User $actor, int $completionPercentage, ?string $evidenceSummary = null): ContractMilestone
    {
        $milestone->forceFill([
            'status' => 'completed',
            'completion_percentage' => min(100, max(0, $completionPercentage)),
            'completed_at' => now()->toDateString(),
            'accepted_by' => $actor->id,
            'accepted_at' => now(),
            'evidence_summary' => $evidenceSummary,
        ])->save();

        $contract = $milestone->contract;
        if ($contract instanceof Contract && $contract->tender instanceof Tender) {
            $this->recordTenderActivity($contract->tender, $actor, 'milestone.completed', 'Contract milestone completed.', contract: $contract, newValues: ['milestone_id' => $milestone->id]);
        }

        MilestoneCompleted::dispatch($milestone, $actor);

        return $milestone->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function approveVariation(VariationOrder $variationOrder, User $actor, array $data): VariationOrder
    {
        $variationOrder->forceFill([
            'status' => 'approved',
            'approved_at' => now()->toDateString(),
            'approved_amount' => $data['approved_amount'] ?? null,
            'schedule_extension_days' => $data['schedule_extension_days'] ?? null,
            'reason' => $data['reason'] ?? null,
        ])->save();

        $contract = $variationOrder->contract;
        if ($contract instanceof Contract && $contract->tender instanceof Tender) {
            $this->recordTenderActivity($contract->tender, $actor, 'variation.approved', 'Variation order approved.', contract: $contract, newValues: ['variation_order_id' => $variationOrder->id]);
        }

        VariationApproved::dispatch($variationOrder, $actor);

        return $variationOrder->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordContractPayment(Contract $contract, User $actor, array $data): void
    {
        $contract->payments()->create($data);

        if ($contract->tender instanceof Tender) {
            $this->recordTenderActivity($contract->tender, $actor, 'contract.payment_recorded', 'Contract payment recorded.', contract: $contract, newValues: ['payment_reference' => $data['payment_reference'] ?? null]);
        }
    }

    public function closeContract(Contract $contract, User $actor, ?string $notes = null): Contract
    {
        return DB::transaction(function () use ($contract, $actor, $notes): Contract {
            $contract->forceFill(['status' => 'closed'])->save();
            $contract->closeout()->create([
                'closed_by' => $actor->id,
                'closed_at' => now(),
                'notes' => $notes,
            ]);

            if ($contract->tender instanceof Tender) {
                $this->recordTenderActivity($contract->tender, $actor, 'contract.closed', $notes ?? 'Contract closed.', contract: $contract);
            }

            ContractClosed::dispatch($contract, $actor);

            return $contract->refresh();
        });
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function recordTenderActivity(Tender $tender, User $actor, string $event, string $description, ?array $oldValues = null, ?array $newValues = null, ?Contract $contract = null): void
    {
        $tender->activities()->create([
            'contract_id' => $contract?->id,
            'actor_id' => $actor->id,
            'event' => $event,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeTenderData(array $data): array
    {
        return array_merge($data, [
            'is_public' => (bool) ($data['is_public'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
    }

    private function statusId(string $slug, int $fallback): int
    {
        return (int) (TenderStatus::query()->where('slug', $slug)->value('id') ?? $fallback);
    }

    private function evaluationCriterion(EvaluationScore $score): ?EvaluationCriterion
    {
        $criterion = $score->criterion;

        return $criterion instanceof EvaluationCriterion ? $criterion : null;
    }

    private function linkBidderToContractorOrganization(BidSubmission $bid): void
    {
        $bidder = $bid->bidderOrganization;
        if (! $bidder instanceof BidderOrganization || $bidder->organization_id !== null || $bidder->registration_number === null) {
            return;
        }

        $organization = Organization::query()
            ->where('registration_number', $bidder->registration_number)
            ->first();

        if ($organization instanceof Organization) {
            $bidder->forceFill(['organization_id' => $organization->id])->save();
        }
    }
}
