<?php

namespace App\Services\Finance;

use App\Models\Budget;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class BudgetListingService
{
    /**
     * @return LengthAwarePaginator<int, Budget>
     */
    public function paginate(Request $request, bool $archived = false, bool $publicOnly = false): LengthAwarePaginator
    {
        $sort = in_array($request->query('sort'), ['current_allocation', 'actual_expenditure', 'currency', 'created_at'], true)
            ? $request->query('sort')
            : 'created_at';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        return Budget::query()
            ->with(['project.agency', 'fiscalYear', 'fundingSource', 'category', 'type', 'status'])
            ->when($archived, fn (Builder $query) => $query->whereNotNull('archived_at'), fn (Builder $query) => $query->whereNull('archived_at'))
            ->when($publicOnly, fn (Builder $query) => $query->whereHas(
                'project',
                fn (Builder $project) => $project->where('is_public', true)->where('is_active', true),
            ))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('notes', 'like', "%{$search}%")
                        ->orWhereHas('project', fn (Builder $query) => $query->where('name', 'like', "%{$search}%")->orWhere('project_code', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('project_id'), fn (Builder $query) => $query->where('project_id', $request->query('project_id')))
            ->when($request->filled('agency_id'), fn (Builder $query) => $query->whereHas('project', fn (Builder $query) => $query->where('agency_id', $request->query('agency_id'))))
            ->when($request->filled('fiscal_year_id'), fn (Builder $query) => $query->where('fiscal_year_id', $request->query('fiscal_year_id')))
            ->when($request->filled('funding_source_id'), fn (Builder $query) => $query->where('funding_source_id', $request->query('funding_source_id')))
            ->when($request->filled('budget_type_id'), fn (Builder $query) => $query->where('budget_type_id', $request->query('budget_type_id')))
            ->when($request->filled('budget_status_id'), fn (Builder $query) => $query->where('budget_status_id', $request->query('budget_status_id')))
            ->when($request->filled('amount_min'), fn (Builder $query) => $query->where('current_allocation', '>=', $request->query('amount_min')))
            ->when($request->filled('amount_max'), fn (Builder $query) => $query->where('current_allocation', '<=', $request->query('amount_max')))
            ->when($request->filled('created_from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->query('created_from')))
            ->when($request->filled('created_to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->query('created_to')))
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();
    }
}
