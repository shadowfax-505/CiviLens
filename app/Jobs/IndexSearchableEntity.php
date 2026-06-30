<?php

namespace App\Jobs;

use App\Contracts\Search\Searchable;
use App\Models\SearchJob;
use App\Services\Search\SearchIndexingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class IndexSearchableEntity implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $searchableType,
        public int $searchableId,
        public string $operation = 'index',
    ) {}

    public function handle(SearchIndexingService $indexing): void
    {
        $record = $this->record();

        if (! $record instanceof Searchable) {
            return;
        }

        $this->operation === 'delete'
            ? $indexing->delete($record)
            : $indexing->index($record);

        SearchJob::query()
            ->where('searchable_type', $this->searchableType)
            ->where('searchable_id', $this->searchableId)
            ->where('operation', $this->operation)
            ->latest()
            ->first()
            ?->forceFill(['status' => 'processed', 'processed_at' => now()])
            ->save();
    }

    public function failed(Throwable $exception): void
    {
        SearchJob::query()
            ->where('searchable_type', $this->searchableType)
            ->where('searchable_id', $this->searchableId)
            ->where('operation', $this->operation)
            ->latest()
            ->first()
            ?->forceFill([
                'status' => 'failed',
                'processed_at' => now(),
                'error_message' => $exception->getMessage(),
            ])
            ->save();
    }

    private function record(): ?Model
    {
        if (! is_a($this->searchableType, Model::class, true)) {
            return null;
        }

        return $this->searchableType::query()->find($this->searchableId);
    }
}
