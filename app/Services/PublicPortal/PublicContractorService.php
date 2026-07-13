<?php

namespace App\Services\PublicPortal;

use App\Models\ContractorProfile;
use App\Models\Organization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PublicContractorService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Organization>
     */
    public function listing(array $filters = []): LengthAwarePaginator
    {
        return Organization::query()
            ->with(['companyType', 'industry', 'profile.registrationStatus'])
            ->where('status', 'active')
            ->whereNull('archived_at')
            ->when($filters['q'] ?? null, function ($builder, string $query): void {
                $builder->where(function ($builder) use ($query): void {
                    $builder->where('legal_name', 'like', '%'.$query.'%')
                        ->orWhere('trade_name', 'like', '%'.$query.'%')
                        ->orWhere('registration_number', 'like', '%'.$query.'%');
                });
            })
            ->when($filters['organization_company_type_id'] ?? null, fn ($builder, int $typeId) => $builder->where('organization_company_type_id', $typeId))
            ->when($filters['organization_industry_id'] ?? null, fn ($builder, int $industryId) => $builder->where('organization_industry_id', $industryId))
            ->orderBy('legal_name')
            ->paginate(12)
            ->withQueryString();
    }

    public function detail(Organization $organization): Organization
    {
        abort_unless($organization->status === 'active' && $organization->archived_at === null, 404);

        $organization->load(['companyType', 'industry', 'country', 'branches' => fn ($query) => $query->where('status', 'active'), 'profile.category', 'profile.classification', 'profile.registrationStatus']);

        abort_unless(
            $organization->profile instanceof ContractorProfile
                && $organization->profile->is_public === true
                && $organization->profile->is_active === true
                && $organization->profile->is_suspended === false
                && $organization->profile->is_blacklisted === false
                && $organization->profile->archived_at === null,
            404,
        );

        return $organization;
    }
}
