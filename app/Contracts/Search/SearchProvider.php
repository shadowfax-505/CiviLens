<?php

namespace App\Contracts\Search;

use App\Models\SearchIndex;
use App\Models\User;
use App\Support\Search\SearchQuery;
use App\Support\Search\SearchResult;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SearchProvider
{
    /**
     * @return LengthAwarePaginator<int, SearchResult>
     */
    public function search(SearchQuery $query, User $user): LengthAwarePaginator;

    public function index(Searchable $searchable): SearchIndex;

    public function delete(Searchable $searchable): void;

    /**
     * @return array<int, string>
     */
    public function suggest(string $prefix, User $user, int $limit = 8): array;
}
