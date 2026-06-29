<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncSearchIndex implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $modelClass,
        public int|string $modelId,
    ) {}

    public function handle(): void
    {
        Log::info('queue.search_index.prepared', [
            'model_class' => $this->modelClass,
            'model_id' => $this->modelId,
        ]);
    }
}
