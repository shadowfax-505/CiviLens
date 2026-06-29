<?php

namespace App\Services\Procurement;

use App\Events\ContractAwarded;
use App\Events\TenderPublished;
use App\Models\Award;
use App\Models\BidSubmission;
use App\Models\Contract;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationScore;
use App\Models\Tender;
use App\Models\TenderStatus;
use App\Models\User;
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
        $this->recordTenderActivity($tender, $actor, 'bid.submitted', 'Bid submission recorded.', newValues: ['reference_number' => $bid->reference_number]);

        return $bid;
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

        $score = EvaluationScore::query()->create(array_merge($data, ['bid_submission_id' => $bidSubmission->id]));
        $this->recordTenderActivity($tender, $actor, 'evaluation.score_recorded', 'Evaluation score recorded.', newValues: ['score' => $score->score]);

        return $score;
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
}
