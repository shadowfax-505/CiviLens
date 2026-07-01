<?php

namespace App\Jobs;

use App\Models\IntelligenceProcessingJob;
use App\Services\Intelligence\ProcessingJobService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class QueueAiReviewPreparation implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $processingJobId) {}

    public function handle(ProcessingJobService $jobs): void
    {
        $job = IntelligenceProcessingJob::query()->find($this->processingJobId);

        if ($job instanceof IntelligenceProcessingJob) {
            $jobs->complete($job);
        }
    }
}
