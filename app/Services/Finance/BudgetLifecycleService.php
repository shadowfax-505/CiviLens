<?php

namespace App\Services\Finance;

use App\Models\Budget;
use App\Models\BudgetTransactionType;
use App\Models\User;

class BudgetLifecycleService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Budget
    {
        return Budget::query()->create($this->normalizeAmounts($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Budget $budget, array $data): Budget
    {
        $budget->update($this->normalizeAmounts($data));

        return $budget;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function revise(Budget $budget, array $data): void
    {
        $previous = (float) $budget->current_allocation;
        $new = (float) $data['new_allocation'];

        $budget->revisions()->create([
            'revision_number' => $budget->revisions()->count() + 1,
            'previous_allocation' => $previous,
            'new_allocation' => $new,
            'difference' => $new - $previous,
            'reason' => $data['reason'],
            'approval_date' => $data['approval_date'] ?? null,
            'approved_by' => $data['approved_by'] ?? null,
        ]);

        $budget->update(['current_allocation' => $new]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function transact(Budget $budget, array $data, User $actor): void
    {
        $type = BudgetTransactionType::query()->findOrFail($data['budget_transaction_type_id']);

        $budget->transactions()->create([
            'budget_transaction_type_id' => $type->id,
            'user_id' => $actor->id,
            'amount' => $data['amount'],
            'transaction_date' => $data['transaction_date'],
            'description' => $data['description'] ?? null,
        ]);

        $amount = (float) $data['amount'];

        if ($type->slug === 'expenditure') {
            $budget->increment('actual_expenditure', $amount);
        } elseif (in_array($type->slug, ['allocation', 'refund'], true)) {
            $budget->increment('current_allocation', $amount);
        } elseif (in_array($type->slug, ['adjustment', 'transfer'], true)) {
            $budget->decrement('current_allocation', $amount);
        }
    }

    public function archive(Budget $budget): void
    {
        $budget->update(['archived_at' => now(), 'is_active' => false]);
    }

    public function restore(Budget $budget): void
    {
        $budget->update(['archived_at' => null, 'is_active' => true]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeAmounts(array $data): array
    {
        return array_merge($data, [
            'reserved_amount' => $data['reserved_amount'] ?? 0,
            'committed_amount' => $data['committed_amount'] ?? 0,
            'actual_expenditure' => $data['actual_expenditure'] ?? 0,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'currency' => strtoupper($data['currency']),
        ]);
    }
}
