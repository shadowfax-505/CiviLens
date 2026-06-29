<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessOcrDocument implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $disk,
        public string $path,
    ) {}

    public function handle(): void
    {
        Log::info('queue.ocr.prepared', [
            'disk' => $this->disk,
            'path' => $this->path,
        ]);
    }
}
