<?php

namespace App\Services\PublicPortal;

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
            ->orderBy('legal_name')
            ->paginate(12)
            ->withQueryString();
    }

    public function detail(Organization $organization): Organization
    {
        abort_unless($organization->status === 'active' && $organization->archived_at === null, 404);

        return $organization->load(['companyType', 'industry', 'profile.category', 'profile.classification', 'profile.registrationStatus']);
    }
}
