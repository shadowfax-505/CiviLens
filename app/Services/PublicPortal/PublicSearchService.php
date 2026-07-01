<?php

namespace App\Services\PublicPortal;

use App\Models\SearchIndex;
use App\Support\Search\SearchResult;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

class PublicSearchService
{
    /**
     * @param  array<string, mixed>  $input
     * @return LengthAwarePaginator<int, SearchResult>
     */
    public function search(array $input): LengthAwarePaginator
    {
        $query = trim((string) ($input['q'] ?? ''));
        $module = filled($input['module'] ?? null) ? (string) $input['module'] : null;
        $page = max(1, (int) ($input['page'] ?? 1));

        $builder = SearchIndex::query()
            ->where('visibility', 'public')
            ->whereIn('module', ['projects', 'procurement', 'documents', 'agencies', 'contractors'])
            ->when($module, fn ($builder) => $builder->where('module', $module));

        if ($query !== '') {
            $builder->where(function ($builder) use ($query): void {
                $builder->where('title', 'like', '%'.$query.'%')
                    ->orWhere('description', 'like', '%'.$query.'%')
                    ->orWhere('search_text', 'like', '%'.$query.'%');
            });
        }

        $results = $builder->latest('indexed_at')->get()
            ->filter(fn (SearchIndex $index): bool => $index->source() !== null)
            ->map(fn (SearchIndex $index): SearchResult => SearchResult::fromIndex($index, 1.0, $query))
            ->values();

        return new Paginator(
            items: $results->forPage($page, 12)->values(),
            total: $results->count(),
            perPage: 12,
            currentPage: $page,
            options: ['path' => request()->url(), 'query' => request()->query()],
        );
    }
}
