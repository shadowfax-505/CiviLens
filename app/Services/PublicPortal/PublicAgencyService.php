<?php

namespace App\Services\PublicPortal;

use App\Models\Agency;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PublicAgencyService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Agency>
     */
    public function listing(array $filters = []): LengthAwarePaginator
    {
        return Agency::query()
            ->with('type')
            ->where('status', 'active')
            ->when($filters['q'] ?? null, function ($builder, string $query): void {
                $builder->where(function ($builder) use ($query): void {
                    $builder->where('name', 'like', '%'.$query.'%')
                        ->orWhere('short_name', 'like', '%'.$query.'%')
                        ->orWhere('description', 'like', '%'.$query.'%');
                });
            })
            ->when($filters['agency_type_id'] ?? null, fn ($builder, int $typeId) => $builder->where('agency_type_id', $typeId))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Agency $agency): array
    {
        abort_unless($agency->status === 'active', 404);

        return [
            'agency' => $agency->load(['type', 'country', 'division', 'district', 'upazila', 'union', 'ward', 'children']),
            'projects' => $agency->projects()->where('is_public', true)->where('is_active', true)->whereNull('archived_at')->latest()->limit(8)->get(),
            'tenders' => $agency->tenders()->where('is_public', true)->where('is_active', true)->whereNull('archived_at')->latest()->limit(8)->get(),
        ];
    }
}
