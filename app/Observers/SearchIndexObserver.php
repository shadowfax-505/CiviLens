<?php

namespace App\Observers;

use App\Contracts\Search\Searchable;
use App\Services\Search\SearchIndexingService;
use Illuminate\Database\Eloquent\Model;

class SearchIndexObserver
{
    public function __construct(private readonly SearchIndexingService $indexing) {}

    public function saved(Model $model): void
    {
        if ($model instanceof Searchable) {
            $this->indexing->index($model);
        }
    }

    public function restored(Model $model): void
    {
        if ($model instanceof Searchable) {
            $this->indexing->index($model);
        }
    }

    public function deleted(Model $model): void
    {
        if ($model instanceof Searchable) {
            $this->indexing->delete($model);
        }
    }
}
