<?php

namespace App\Services\Procurement;

use App\Models\Tender;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TenderListingService
{
    /**
     * @return LengthAwarePaginator<int, Tender>
     */
    public function paginate(Request $request, bool $archived = false): LengthAwarePaginator
    {
        $sort = in_array($request->query('sort'), ['tender_number', 'title', 'published_at', 'closing_at', 'created_at'], true)
            ? $request->query('sort')
            : 'created_at';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        return Tender::query()
            ->with(['project', 'budget.fiscalYear', 'agency', 'method', 'category', 'status', 'awards.bidSubmission.bidderOrganization', 'awards.contract'])
            ->when($archived, fn (Builder $query) => $query->whereNotNull('archived_at'), fn (Builder $query) => $query->whereNull('archived_at'))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('tender_number', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhereHas('project', fn (Builder $query) => $query->where('name', 'like', "%{$search}%")->orWhere('project_code', 'like', "%{$search}%"))
                        ->orWhereHas('agency', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('awards.bidSubmission.bidderOrganization', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('project_id'), fn (Builder $query) => $query->where('project_id', $request->query('project_id')))
            ->when($request->filled('agency_id'), fn (Builder $query) => $query->where('agency_id', $request->query('agency_id')))
            ->when($request->filled('procurement_method_id'), fn (Builder $query) => $query->where('procurement_method_id', $request->query('procurement_method_id')))
            ->when($request->filled('tender_category_id'), fn (Builder $query) => $query->where('tender_category_id', $request->query('tender_category_id')))
            ->when($request->filled('tender_status_id'), fn (Builder $query) => $query->where('tender_status_id', $request->query('tender_status_id')))
            ->when($request->filled('fiscal_year_id'), fn (Builder $query) => $query->whereHas('budget', fn (Builder $query) => $query->where('fiscal_year_id', $request->query('fiscal_year_id'))))
            ->when($request->filled('winning_bidder'), fn (Builder $query) => $query->whereHas('awards.bidSubmission.bidderOrganization', fn (Builder $query) => $query->where('name', 'like', '%'.$request->query('winning_bidder').'%')))
            ->when($request->filled('contract_status'), fn (Builder $query) => $query->whereHas('awards.contract', fn (Builder $query) => $query->where('status', $request->query('contract_status'))))
            ->when($request->filled('published_from'), fn (Builder $query) => $query->whereDate('published_at', '>=', $request->query('published_from')))
            ->when($request->filled('published_to'), fn (Builder $query) => $query->whereDate('published_at', '<=', $request->query('published_to')))
            ->when($request->filled('budget_min'), fn (Builder $query) => $query->whereHas('budget', fn (Builder $query) => $query->where('current_allocation', '>=', $request->query('budget_min'))))
            ->when($request->filled('budget_max'), fn (Builder $query) => $query->whereHas('budget', fn (Builder $query) => $query->where('current_allocation', '<=', $request->query('budget_max'))))
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();
    }
}
