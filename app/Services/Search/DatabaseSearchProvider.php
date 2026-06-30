<?php

namespace App\Services\Search;

use App\Contracts\Search\Searchable;
use App\Contracts\Search\SearchProvider;
use App\Models\SearchDocument;
use App\Models\SearchIndex;
use App\Models\SearchKeyword;
use App\Models\SearchPopularity;
use App\Models\User;
use App\Support\Search\SearchQuery;
use App\Support\Search\SearchResult;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class DatabaseSearchProvider implements SearchProvider
{
    public function __construct(private readonly SearchRankingService $ranking) {}

    public function search(SearchQuery $query, User $user): LengthAwarePaginatorContract
    {
        $builder = SearchIndex::query()
            ->with(['keywords', 'popularity'])
            ->when($query->module, fn ($builder) => $builder->where('module', $query->module))
            ->when($query->filters['status'] ?? null, fn ($builder, string $status) => $builder->where('status', $status))
            ->when($query->filters['visibility'] ?? null, fn ($builder, string $visibility) => $builder->where('visibility', $visibility));

        if ($query->query !== '') {
            $needle = '%'.$query->query.'%';
            $builder->where(function ($builder) use ($needle): void {
                $builder->where('title', 'like', $needle)
                    ->orWhere('description', 'like', $needle)
                    ->orWhere('search_text', 'like', $needle)
                    ->orWhereHas('keywords', fn ($keywordQuery) => $keywordQuery->where('keyword', 'like', $needle));
            });
        }

        /** @var Collection<int, SearchIndex> $indexes */
        $indexes = $builder->limit(1000)->get()
            ->filter(fn (SearchIndex $index): bool => $this->canView($user, $index))
            ->values();

        /** @var Collection<int, SearchResult> $results */
        $results = $indexes->map(fn (SearchIndex $index): SearchResult => SearchResult::fromIndex(
            $index,
            $this->ranking->score($index, $query->query),
            $query->query,
        ));

        $results = $this->sort($results, $query);

        return new LengthAwarePaginator(
            items: $results->forPage($query->page, $query->perPage)->values(),
            total: $results->count(),
            perPage: $query->perPage,
            currentPage: $query->page,
            options: [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );
    }

    public function index(Searchable $searchable): SearchIndex
    {
        if (! $searchable instanceof Model) {
            throw new RuntimeException('Searchable entities must be Eloquent models.');
        }

        $keywords = $this->normalizeKeywords($searchable);
        $searchText = implode(' ', array_filter([
            $searchable->searchTitle(),
            $searchable->searchDescription(),
            implode(' ', $keywords),
            json_encode($searchable->searchRelations()),
        ]));

        /** @var SearchIndex $index */
        $index = SearchIndex::query()->updateOrCreate(
            [
                'searchable_type' => $searchable::class,
                'searchable_id' => $searchable->getKey(),
            ],
            [
                'module' => $searchable->searchModule(),
                'title' => $searchable->searchTitle(),
                'description' => $searchable->searchDescription(),
                'url' => $searchable->searchUrl(),
                'visibility' => $searchable->searchVisibility(),
                'status' => $searchable->searchStatus(),
                'search_text' => $searchText,
                'entity_summary' => $searchable->searchDescription(),
                'search_vector' => $searchText,
                'entity_keywords' => $keywords,
                'metadata' => $searchable->searchMetadata(),
                'indexed_at' => now(),
            ],
        );

        SearchDocument::query()->updateOrCreate(
            ['search_index_id' => $index->id, 'locale' => 'en'],
            [
                'title' => $index->title,
                'excerpt' => $index->description,
                'body_hash' => hash('sha256', $searchText),
                'indexed_payload' => [
                    'module' => $index->module,
                    'relations' => $searchable->searchRelations(),
                    'metadata' => $searchable->searchMetadata(),
                ],
            ],
        );

        SearchKeyword::query()->where('search_index_id', $index->id)->delete();

        foreach ($keywords as $keyword) {
            SearchKeyword::query()->create([
                'search_index_id' => $index->id,
                'keyword' => $keyword,
                'weight' => $keyword === mb_strtolower($searchable->searchTitle()) ? 10 : 1,
            ]);
        }

        SearchPopularity::query()->firstOrCreate(['search_index_id' => $index->id]);

        return $index->refresh();
    }

    public function delete(Searchable $searchable): void
    {
        if (! $searchable instanceof Model) {
            return;
        }

        SearchIndex::query()
            ->where('searchable_type', $searchable::class)
            ->where('searchable_id', $searchable->getKey())
            ->delete();
    }

    public function suggest(string $prefix, User $user, int $limit = 8): array
    {
        $prefix = mb_strtolower(trim($prefix));

        if ($prefix === '') {
            return [];
        }

        return SearchKeyword::query()
            ->with('index')
            ->where('keyword', 'like', $prefix.'%')
            ->orderByDesc('weight')
            ->limit(50)
            ->get()
            ->filter(fn (SearchKeyword $keyword): bool => $keyword->index !== null && $this->canView($user, $keyword->index))
            ->pluck('keyword')
            ->unique()
            ->values()
            ->take($limit)
            ->all();
    }

    private function canView(User $user, SearchIndex $index): bool
    {
        if ($user->hasRole(config('civiclens.roles.admin'))) {
            return true;
        }

        $source = $index->source();

        if ($source === null) {
            return false;
        }

        return Gate::forUser($user)->allows('view', $source)
            || Gate::forUser($user)->allows('viewAny', $source::class);
    }

    /**
     * @return array<int, string>
     */
    private function normalizeKeywords(Searchable $searchable): array
    {
        $phrases = collect(array_merge([$searchable->searchTitle()], $searchable->searchKeywords()))
            ->filter(fn (mixed $keyword): bool => is_string($keyword) && trim($keyword) !== '')
            ->map(fn (string $keyword): string => mb_strtolower(trim($keyword)))
            ->values();

        $tokens = $phrases
            ->flatMap(fn (string $keyword): array => preg_split('/[^[:alnum:]]+/u', $keyword) ?: [])
            ->filter(fn (mixed $keyword): bool => is_string($keyword) && mb_strlen($keyword) >= 3)
            ->map(fn (string $keyword): string => mb_strtolower(trim($keyword)));

        return $phrases
            ->merge($tokens)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, SearchResult>  $results
     * @return Collection<int, SearchResult>
     */
    private function sort(Collection $results, SearchQuery $query): Collection
    {
        $directionDescending = $query->direction === 'desc';

        return match ($query->sort) {
            'title' => $results->sortBy('title', SORT_REGULAR, $directionDescending)->values(),
            'module' => $results->sortBy('module', SORT_REGULAR, $directionDescending)->values(),
            'status' => $results->sortBy('status', SORT_REGULAR, $directionDescending)->values(),
            default => $results->sortBy('score', SORT_REGULAR, true)->values(),
        };
    }
}
