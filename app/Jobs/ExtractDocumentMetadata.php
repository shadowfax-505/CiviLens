<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ExtractDocumentMetadata implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $documentId) {}

    public function handle(): void
    {
        Log::info('queue.document_metadata_extraction.prepared', ['document_id' => $this->documentId]);
    }
}
