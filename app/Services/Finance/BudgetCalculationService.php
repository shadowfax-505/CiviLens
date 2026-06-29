<?php

namespace App\Services\Finance;

use App\Models\Budget;

class BudgetCalculationService
{
    public function remainingBalance(Budget $budget): float
    {
        return $budget->remaining_balance;
    }

    public function utilizationPercentage(Budget $budget): float
    {
        return $budget->utilization_percentage;
    }

    /**
     * @return array<string, float|int|string>
     */
    public function dashboardSummary(): array
    {
        $budgets = Budget::query()->whereNull('archived_at');
        $total = (float) (clone $budgets)->sum('current_allocation');
        $spent = (float) (clone $budgets)->sum('actual_expenditure');
        $reserved = (float) (clone $budgets)->sum('reserved_amount');
        $committed = (float) (clone $budgets)->sum('committed_amount');
        $remaining = $total - $spent - $reserved - $committed;

        return [
            'total_budget' => $total,
            'allocated_budget' => $total,
            'spent_budget' => $spent,
            'remaining_budget' => $remaining,
            'utilization_percentage' => $total > 0 ? round(($spent / $total) * 100, 2) : 0.0,
            'revision_count' => Budget::query()->withCount('revisions')->get()->sum('revisions_count'),
            'financial_health' => $total > 0 && ($spent / $total) <= 0.9 ? 'Stable' : 'Watch',
        ];
    }
}
