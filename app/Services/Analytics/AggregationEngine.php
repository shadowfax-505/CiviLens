<?php

namespace App\Services\Analytics;

use App\Models\Budget;
use App\Models\Project;
use App\Models\Tender;
use App\Support\Analytics\AnalyticsFilters;
use Illuminate\Database\Eloquent\Builder;

class AggregationEngine
{
    /**
     * @return Builder<Project>
     */
    public function projects(AnalyticsFilters $filters): Builder
    {
        return Project::query()
            ->when($filters->projectId, fn (Builder $query) => $query->whereKey($filters->projectId))
            ->when($filters->agencyId, fn (Builder $query) => $query->where('agency_id', $filters->agencyId))
            ->when($filters->fiscalYearId, fn (Builder $query) => $query->where('fiscal_year_id', $filters->fiscalYearId))
            ->when($filters->fundingSourceId, fn (Builder $query) => $query->where('funding_source_id', $filters->fundingSourceId))
            ->when($filters->districtId, fn (Builder $query) => $query->where('district_id', $filters->districtId))
            ->when($filters->divisionId, fn (Builder $query) => $query->where('division_id', $filters->divisionId))
            ->when($filters->dateFrom, fn (Builder $query) => $query->whereDate('created_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn (Builder $query) => $query->whereDate('created_at', '<=', $filters->dateTo));
    }

    /**
     * @return Builder<Budget>
     */
    public function budgets(AnalyticsFilters $filters): Builder
    {
        return Budget::query()
            ->when($filters->budgetId, fn (Builder $query) => $query->whereKey($filters->budgetId))
            ->when($filters->projectId, fn (Builder $query) => $query->where('project_id', $filters->projectId))
            ->when($filters->fiscalYearId, fn (Builder $query) => $query->where('fiscal_year_id', $filters->fiscalYearId))
            ->when($filters->fundingSourceId, fn (Builder $query) => $query->where('funding_source_id', $filters->fundingSourceId))
            ->when($filters->agencyId, fn (Builder $query) => $query->whereHas('project', fn (Builder $project) => $project->where('agency_id', $filters->agencyId)))
            ->when($filters->districtId, fn (Builder $query) => $query->whereHas('project', fn (Builder $project) => $project->where('district_id', $filters->districtId)))
            ->when($filters->divisionId, fn (Builder $query) => $query->whereHas('project', fn (Builder $project) => $project->where('division_id', $filters->divisionId)))
            ->when($filters->dateFrom, fn (Builder $query) => $query->whereDate('created_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn (Builder $query) => $query->whereDate('created_at', '<=', $filters->dateTo));
    }

    /**
     * @return Builder<Tender>
     */
    public function tenders(AnalyticsFilters $filters): Builder
    {
        return Tender::query()
            ->when($filters->projectId, fn (Builder $query) => $query->where('project_id', $filters->projectId))
            ->when($filters->budgetId, fn (Builder $query) => $query->where('budget_id', $filters->budgetId))
            ->when($filters->agencyId, fn (Builder $query) => $query->where('agency_id', $filters->agencyId))
            ->when($filters->procurementMethodId, fn (Builder $query) => $query->where('procurement_method_id', $filters->procurementMethodId))
            ->when($filters->dateFrom, fn (Builder $query) => $query->whereDate('created_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn (Builder $query) => $query->whereDate('created_at', '<=', $filters->dateTo));
    }
}
