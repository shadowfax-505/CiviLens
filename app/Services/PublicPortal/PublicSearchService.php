<?php

namespace App\Services\PublicPortal;

use App\Models\Agency;
use App\Models\Document;
use App\Models\Organization;
use App\Models\Project;
use App\Models\SearchIndex;
use App\Models\Tender;
use App\Support\Search\SearchResult;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

class PublicSearchService
{
    /**
     * @param  array<string, mixed>  $input
     * @return LengthAwarePaginator<int, SearchResult>
     */
    public function search(array $input): LengthAwarePaginator
    {
        $query = mb_substr(trim((string) ($input['q'] ?? '')), 0, 120);
        $module = filled($input['module'] ?? null) ? (string) $input['module'] : null;
        $page = max(1, (int) ($input['page'] ?? 1));
        $perPage = 12;
        $firstResult = (($page - 1) * $perPage) + 1;
        $lastResult = $firstResult + $perPage - 1;

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

        $visibility = app(PublicVisibilityService::class);

        $items = [];
        $total = 0;

        foreach ($builder->orderByDesc('indexed_at')->orderByDesc('id')->limit(1000)->cursor() as $index) {
            if (! $this->sourceIsPublic($index->source(), $visibility)) {
                continue;
            }

            $total++;

            if ($total >= $firstResult && $total <= $lastResult) {
                $items[] = SearchResult::fromIndex($index, 1.0, $query);
            }
        }

        return new Paginator(
            items: collect($items),
            total: $total,
            perPage: $perPage,
            currentPage: $page,
            options: ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    private function sourceIsPublic(?Model $source, PublicVisibilityService $visibility): bool
    {
        return match (true) {
            $source instanceof Project => $visibility->projectIsPublic($source),
            $source instanceof Agency => $visibility->agencyIsPublic($source),
            $source instanceof Organization => $visibility->organizationIsPublic($source),
            $source instanceof Tender => $visibility->tenderIsPublic($source),
            $source instanceof Document => $visibility->documentIsPublic($source),
            default => false,
        };
    }
}
