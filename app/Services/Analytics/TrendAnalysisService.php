<?php

namespace App\Services\Analytics;

use App\Models\BudgetTransaction;
use App\Models\Project;
use App\Support\Analytics\AnalyticsFilters;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TrendAnalysisService
{
    /**
     * @return array<string, float>
     */
    public function monthlySpending(AnalyticsFilters $filters): array
    {
        return BudgetTransaction::query()
            ->when($filters->dateFrom, fn ($query) => $query->whereDate('transaction_date', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn ($query) => $query->whereDate('transaction_date', '<=', $filters->dateTo))
            ->orderBy('transaction_date')
            ->get(['transaction_date', 'amount'])
            ->groupBy(fn (BudgetTransaction $transaction): string => Carbon::parse($transaction->transaction_date)->format('Y-m'))
            ->map(fn ($transactions): float => round((float) $transactions->sum('amount'), 2))
            ->all();
    }

    /**
     * @return array<string, int>
     */
    public function projectStatusDistribution(AnalyticsFilters $filters): array
    {
        return Project::query()
            ->join('project_statuses', 'projects.project_status_id', '=', 'project_statuses.id')
            ->when($filters->agencyId, fn ($query) => $query->where('projects.agency_id', $filters->agencyId))
            ->select('project_statuses.name')
            ->selectRaw('count(*) as total')
            ->groupBy('project_statuses.name')
            ->orderBy('project_statuses.name')
            ->pluck('total', 'name')
            ->map(fn (mixed $value): int => (int) $value)
            ->all();
    }

    /**
     * @return array<string, float>
     */
    public function investmentByDistrict(AnalyticsFilters $filters): array
    {
        return DB::table('budgets')
            ->join('projects', 'budgets.project_id', '=', 'projects.id')
            ->leftJoin('districts', 'projects.district_id', '=', 'districts.id')
            ->when($filters->fiscalYearId, fn ($query) => $query->where('budgets.fiscal_year_id', $filters->fiscalYearId))
            ->selectRaw("coalesce(districts.name, 'Unassigned') as district")
            ->selectRaw('sum(budgets.current_allocation) as total')
            ->groupBy('district')
            ->orderByDesc('total')
            ->limit(8)
            ->pluck('total', 'district')
            ->map(fn (mixed $value): float => round((float) $value, 2))
            ->all();
    }
}
