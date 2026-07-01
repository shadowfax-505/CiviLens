<?php

namespace App\Jobs;

use App\Models\IntelligenceIndicator;
use App\Models\IntelligenceProcessingJob;
use App\Services\Search\SearchIndexingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncIndicatorToSearch implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $processingJobId) {}

    public function handle(SearchIndexingService $search): void
    {
        $job = IntelligenceProcessingJob::query()->find($this->processingJobId);

        if (! $job instanceof IntelligenceProcessingJob || $job->target_type !== IntelligenceIndicator::class) {
            return;
        }

        $indicator = IntelligenceIndicator::query()->find($job->target_id);

        if ($indicator instanceof IntelligenceIndicator) {
            $search->index($indicator);
            $job->update(['status' => 'completed', 'completed_at' => now()]);
        }
    }
}
