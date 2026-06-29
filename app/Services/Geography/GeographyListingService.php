<?php

namespace App\Services\Geography;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class GeographyListingService
{
    /**
     * @param  class-string<Model>  $model
     * @param  array<int, string>  $searchColumns
     * @param  array<string, string>  $filters
     * @param  array<int, string>  $allowedSorts
     * @param  array<int, string>  $with
     * @return LengthAwarePaginator<int, Model>
     */
    public function paginate(Request $request, string $model, array $searchColumns, array $filters = [], array $allowedSorts = ['name', 'created_at'], array $with = []): LengthAwarePaginator
    {
        $sort = in_array($request->query('sort'), $allowedSorts, true) ? $request->query('sort') : 'name';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        /** @var Builder<Model> $query */
        $query = $model::query()->with($with);

        $query->when($request->filled('search'), function (Builder $query) use ($request, $searchColumns): void {
            $search = $request->string('search')->toString();
            $query->where(function (Builder $query) use ($search, $searchColumns): void {
                foreach ($searchColumns as $index => $column) {
                    if ($index === 0) {
                        $query->where($column, 'like', "%{$search}%");

                        continue;
                    }

                    $query->orWhere($column, 'like', "%{$search}%");
                }
            });
        });

        foreach ($filters as $queryKey => $column) {
            $query->when($request->filled($queryKey), fn (Builder $query) => $query->where($column, $request->query($queryKey)));
        }

        return $query->orderBy($sort, $direction)->paginate(15)->withQueryString();
    }
}
