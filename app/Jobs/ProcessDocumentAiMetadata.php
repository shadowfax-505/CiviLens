<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessDocumentAiMetadata implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $documentId) {}

    public function handle(): void
    {
        Log::info('queue.document_ai_metadata.prepared', ['document_id' => $this->documentId]);
    }
}
