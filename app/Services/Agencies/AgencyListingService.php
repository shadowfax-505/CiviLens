<?php

namespace App\Services\Agencies;

use App\Models\Agency;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AgencyListingService
{
    public function paginate(Request $request): LengthAwarePaginator
    {
        $sort = in_array($request->query('sort'), ['name', 'short_name', 'status', 'created_at'], true)
            ? $request->query('sort')
            : 'name';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        return Agency::query()
            ->with(['type', 'parent', 'country', 'users'])
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('short_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->query('status')))
            ->when($request->filled('agency_type_id'), fn (Builder $query) => $query->where('agency_type_id', $request->query('agency_type_id')))
            ->when($request->filled('country_id'), fn (Builder $query) => $query->where('country_id', $request->query('country_id')))
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();
    }
}
