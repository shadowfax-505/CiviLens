<?php

namespace App\Jobs;

use App\Contracts\Search\Searchable;
use App\Services\Search\SearchIndexingService;
use App\Services\Search\SearchRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;

class ReindexSearchRegistry implements ShouldQueue
{
    use Queueable;

    public function handle(SearchRegistry $registry, SearchIndexingService $indexing): void
    {
        foreach ($registry->searchableClasses() as $class) {
            $class::query()->each(function (Model $record) use ($indexing): void {
                if ($record instanceof Searchable) {
                    $indexing->index($record);
                }
            });
        }
    }
}
