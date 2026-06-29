<?php

namespace App\Services\Contractors;

use App\Models\Organization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ContractorListingService
{
    /**
     * @return LengthAwarePaginator<int, Organization>
     */
    public function paginate(Request $request, bool $archived = false): LengthAwarePaginator
    {
        $sort = in_array($request->query('sort'), ['legal_name', 'registration_number', 'status', 'created_at'], true)
            ? $request->query('sort')
            : 'created_at';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        return Organization::query()
            ->with(['companyType', 'industry', 'country', 'profile.category', 'profile.riskLevel'])
            ->when($archived, fn (Builder $query) => $query->whereNotNull('archived_at'), fn (Builder $query) => $query->whereNull('archived_at'))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('legal_name', 'like', "%{$search}%")
                        ->orWhere('trade_name', 'like', "%{$search}%")
                        ->orWhere('registration_number', 'like', "%{$search}%")
                        ->orWhere('tax_identification_number', 'like', "%{$search}%")
                        ->orWhereHas('profile.licenses', fn (Builder $query) => $query->where('license_number', 'like', "%{$search}%"))
                        ->orWhereHas('profile.certifications', fn (Builder $query) => $query->where('certificate_number', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('registration_number'), fn (Builder $query) => $query->where('registration_number', 'like', '%'.$request->query('registration_number').'%'))
            ->when($request->filled('organization_company_type_id'), fn (Builder $query) => $query->where('organization_company_type_id', $request->query('organization_company_type_id')))
            ->when($request->filled('organization_industry_id'), fn (Builder $query) => $query->where('organization_industry_id', $request->query('organization_industry_id')))
            ->when($request->filled('country_id'), fn (Builder $query) => $query->where('country_id', $request->query('country_id')))
            ->when($request->filled('district_id'), fn (Builder $query) => $query->where('district_id', $request->query('district_id')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->query('status')))
            ->when($request->filled('risk_level_id'), fn (Builder $query) => $query->whereHas('profile', fn (Builder $query) => $query->where('contractor_risk_level_id', $request->query('risk_level_id'))))
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();
    }
}
