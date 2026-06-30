<?php

namespace App\Services\Search;

use App\Contracts\Search\Searchable;
use App\Contracts\Search\SearchProvider;
use App\Events\EntityIndexed;
use App\Events\EntityReindexed;
use App\Jobs\IndexSearchableEntity;
use App\Models\SearchIndex;
use App\Models\SearchJob;
use Illuminate\Database\Eloquent\Model;

class SearchIndexingService
{
    public function __construct(private readonly SearchProvider $provider) {}

    public function index(Searchable $searchable): SearchIndex
    {
        $existing = $searchable instanceof Model && SearchIndex::query()
            ->where('searchable_type', $searchable::class)
            ->where('searchable_id', $searchable->getKey())
            ->exists();

        $index = $this->provider->index($searchable);

        $existing
            ? EntityReindexed::dispatch($index)
            : EntityIndexed::dispatch($index);

        return $index;
    }

    public function queue(Searchable $searchable, string $operation = 'index'): void
    {
        if (! $searchable instanceof Model) {
            return;
        }

        SearchJob::query()->create([
            'searchable_type' => $searchable::class,
            'searchable_id' => $searchable->getKey(),
            'operation' => $operation,
            'status' => 'queued',
            'queued_at' => now(),
        ]);

        IndexSearchableEntity::dispatch($searchable::class, (int) $searchable->getKey(), $operation);
    }

    public function delete(Searchable $searchable): void
    {
        $this->provider->delete($searchable);
    }
}
