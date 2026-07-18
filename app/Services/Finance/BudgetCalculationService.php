<?php

namespace App\Services\Finance;

use App\Models\Budget;
use App\Models\Project;
use Illuminate\Support\Collection;

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
    public function dashboardSummary(bool $publicOnly = false): array
    {
        $budgets = Budget::query()
            ->whereNull('archived_at')
            ->when($publicOnly, fn ($query) => $query->whereHas(
                'project',
                fn ($project) => $project->where('is_public', true)->where('is_active', true),
            ));
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
            'revision_count' => (clone $budgets)->withCount('revisions')->get()->sum('revisions_count'),
            'financial_health' => $total > 0 && ($spent / $total) <= 0.9 ? 'Stable' : 'Watch',
        ];
    }

    /**
     * Budget totals grouped by fiscal year, broken down per project and per
     * agency (via each project's owning agency), plus a combined total for
     * the fiscal year. Archived budgets are excluded, matching dashboardSummary().
     * Pass $publicOnly to scope this to budgets of public, active projects only
     * (used by the citizen-facing dashboard so internal project budgets never leak).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function fiscalYearSummaries(bool $publicOnly = false): Collection
    {
        $budgets = Budget::query()
            ->whereNull('archived_at')
            ->whereNotNull('fiscal_year_id')
            ->when($publicOnly, fn ($query) => $query->whereHas(
                'project',
                fn ($project) => $project->where('is_public', true)->where('is_active', true),
            ))
            ->with(['project.agency', 'fiscalYear'])
            ->get();

        return $budgets
            ->groupBy('fiscal_year_id')
            ->map(/** @return array<string, mixed> */ function ($fiscalYearBudgets): array {
                $projects = $fiscalYearBudgets
                    ->groupBy('project_id')
                    ->map(/** @return array<string, mixed> */ function ($projectBudgets): array {
                        /** @var Budget $first */
                        $first = $projectBudgets->first();

                        return [
                            'project' => $first->project,
                            'total_allocation' => (float) $projectBudgets->sum('current_allocation'),
                            'total_spent' => (float) $projectBudgets->sum('actual_expenditure'),
                        ];
                    })
                    ->sortByDesc('total_allocation')
                    ->values();

                $agencies = $fiscalYearBudgets
                    ->groupBy(function (Budget $budget): int|string {
                        /** @var Project|null $project */
                        $project = $budget->project;

                        return $project->agency_id ?? 'unassigned';
                    })
                    ->map(/** @return array<string, mixed> */ function ($agencyBudgets): array {
                        /** @var Budget $first */
                        $first = $agencyBudgets->first();
                        /** @var Project|null $project */
                        $project = $first->project;

                        return [
                            'agency' => $project?->agency,
                            'total_allocation' => (float) $agencyBudgets->sum('current_allocation'),
                            'total_spent' => (float) $agencyBudgets->sum('actual_expenditure'),
                        ];
                    })
                    ->sortByDesc('total_allocation')
                    ->values();

                $totalAllocation = (float) $fiscalYearBudgets->sum('current_allocation');
                $totalSpent = (float) $fiscalYearBudgets->sum('actual_expenditure');
                /** @var Budget $firstBudget */
                $firstBudget = $fiscalYearBudgets->first();

                /** @var array<string, mixed> $result */
                $result = [
                    'fiscal_year' => $firstBudget->fiscalYear,
                    'total_allocation' => $totalAllocation,
                    'total_spent' => $totalSpent,
                    'total_remaining' => $totalAllocation - $totalSpent,
                    'projects' => $projects,
                    'agencies' => $agencies,
                ];

                return $result;
            })
            ->sortByDesc(fn (array $summary): string => optional($summary['fiscal_year'])->starts_on?->format('Y-m-d') ?? '')
            ->values();
    }
}
