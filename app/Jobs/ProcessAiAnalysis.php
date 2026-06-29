<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessAiAnalysis implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $subjectType,
        public int|string $subjectId,
    ) {}

    public function handle(): void
    {
        Log::info('queue.ai.prepared', [
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
        ]);
    }
}
