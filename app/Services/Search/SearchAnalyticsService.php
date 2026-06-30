<?php

namespace App\Services\Search;

use App\Events\AnalyticsUpdated;
use App\Events\SavedSearchCreated;
use App\Models\SavedSearch;
use App\Models\SearchClick;
use App\Models\SearchHistory;
use App\Models\SearchIndex;
use App\Models\SearchPopularity;
use App\Models\User;
use App\Support\Search\SearchQuery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SearchAnalyticsService
{
    public function recordSearch(User $user, SearchQuery $query, int $resultsCount, int $latencyMs, bool $successful): void
    {
        SearchHistory::query()->create([
            'user_id' => $user->id,
            'query' => $query->query,
            'module' => $query->module,
            'filters' => $query->filters,
            'results_count' => $resultsCount,
            'latency_ms' => $latencyMs,
            'successful' => $successful,
        ]);

        Cache::forget('search:analytics:summary');
        AnalyticsUpdated::dispatch();
    }

    public function saveSearch(User $user, string $name, SearchQuery $query): SavedSearch
    {
        /** @var SavedSearch $savedSearch */
        $savedSearch = SavedSearch::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'query' => $query->query,
            'module' => $query->module,
            'filters' => $query->filters,
            'sort' => $query->sort,
            'direction' => $query->direction,
        ]);

        SavedSearchCreated::dispatch($savedSearch);

        return $savedSearch;
    }

    public function recordClick(User $user, SearchIndex $index, string $query, int $position): void
    {
        SearchClick::query()->create([
            'search_index_id' => $index->id,
            'user_id' => $user->id,
            'query' => $query,
            'result_position' => $position,
            'clicked_at' => now(),
        ]);

        SearchPopularity::query()->updateOrCreate(
            ['search_index_id' => $index->id],
            [
                'clicks_count' => DB::raw('clicks_count + 1'),
                'popularity_score' => DB::raw('popularity_score + 2'),
                'last_clicked_at' => now(),
            ],
        );

        Cache::forget('search:analytics:summary');
        AnalyticsUpdated::dispatch('clicks');
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return Cache::remember('search:analytics:summary', now()->addMinutes(10), fn (): array => [
            'total_searches' => SearchHistory::query()->count(),
            'failed_searches' => SearchHistory::query()->where('successful', false)->count(),
            'average_latency_ms' => round((float) SearchHistory::query()->avg('latency_ms'), 2),
            'saved_searches' => SavedSearch::query()->count(),
            'clicks' => SearchClick::query()->count(),
            'top_terms' => SearchHistory::query()
                ->select('query', DB::raw('count(*) as aggregate'))
                ->whereNotNull('query')
                ->where('query', '!=', '')
                ->groupBy('query')
                ->orderByDesc('aggregate')
                ->limit(10)
                ->pluck('aggregate', 'query')
                ->map(fn (mixed $count): int => (int) $count)
                ->all(),
        ]);
    }
}
