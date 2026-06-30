<?php

namespace App\Services\Search;

use App\Contracts\Search\SearchProvider;
use App\Events\SearchExecuted;
use App\Events\SearchFailed;
use App\Models\User;
use App\Support\Search\SearchQuery;
use App\Support\Search\SearchResult;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Throwable;

class SearchManager
{
    public function __construct(
        private readonly SearchProvider $provider,
        private readonly SearchAnalyticsService $analytics,
    ) {}

    /**
     * @return LengthAwarePaginator<int, SearchResult>
     */
    public function search(SearchQuery $query, User $user): LengthAwarePaginator
    {
        $startedAt = microtime(true);

        try {
            $results = $this->provider->search($query, $user);
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

            $this->analytics->recordSearch($user, $query, $results->total(), $latencyMs, true);
            SearchExecuted::dispatch($query, $user, $results->total(), $latencyMs);

            return $results;
        } catch (Throwable $exception) {
            $this->analytics->recordSearch($user, $query, 0, (int) round((microtime(true) - $startedAt) * 1000), false);
            SearchFailed::dispatch($query, $user, $exception);

            throw $exception;
        }
    }
}
