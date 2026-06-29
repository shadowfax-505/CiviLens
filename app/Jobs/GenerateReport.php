<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateReport implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public string $reportType,
        public array $filters = [],
    ) {}

    public function handle(): void
    {
        Log::info('queue.report.prepared', [
            'report_type' => $this->reportType,
        ]);
    }
}
