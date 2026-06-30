<?php

namespace App\Services\Search;

use App\Contracts\Search\SearchProvider;
use App\Events\SuggestionGenerated;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class SearchSuggestionService
{
    public function __construct(private readonly SearchProvider $provider) {}

    /**
     * @return array<int, string>
     */
    public function suggest(string $prefix, User $user, int $limit = 8): array
    {
        $prefix = mb_strtolower(trim($prefix));
        $cacheKey = 'search:suggestions:'.md5($user->id.'|'.$prefix);

        $suggestions = Cache::remember(
            $cacheKey,
            now()->addMinutes((int) config('civiclens.search.cache_ttl_minutes', 10)),
            fn (): array => $this->provider->suggest($prefix, $user, $limit),
        );

        SuggestionGenerated::dispatch($prefix, $user, $suggestions);

        return $suggestions;
    }
}
