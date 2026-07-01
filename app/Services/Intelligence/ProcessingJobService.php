<?php

namespace App\Services\Intelligence;

use App\Events\IntelligenceProcessingJobCompleted;
use App\Events\IntelligenceProcessingJobFailed;
use App\Events\IntelligenceProcessingJobQueued;
use App\Jobs\QueueAiReviewPreparation;
use App\Jobs\QueueDocumentOcrPreparation;
use App\Jobs\SyncIndicatorToSearch;
use App\Models\IntelligenceActivity;
use App\Models\IntelligenceProcessingJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ProcessingJobService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function queue(Model $target, string $jobType, ?User $user = null, array $payload = []): IntelligenceProcessingJob
    {
        $job = IntelligenceProcessingJob::query()->create([
            'target_type' => $target::class,
            'target_id' => $target->getKey(),
            'job_type' => $jobType,
            'status' => 'queued',
            'attempts' => 0,
            'payload' => $payload,
            'queued_by' => $user?->id,
            'queued_at' => now(),
        ]);

        IntelligenceActivity::query()->create([
            'intelligence_processing_job_id' => $job->id,
            'actor_id' => $user?->id,
            'event' => 'processing_job_queued',
            'description' => $jobType.' queued.',
            'properties' => ['target_type' => $target::class, 'target_id' => $target->getKey()],
            'occurred_at' => now(),
        ]);

        match ($jobType) {
            'ocr_preparation' => QueueDocumentOcrPreparation::dispatch($job->id),
            'ai_review_preparation' => QueueAiReviewPreparation::dispatch($job->id),
            'search_sync' => SyncIndicatorToSearch::dispatch($job->id),
            default => null,
        };

        IntelligenceProcessingJobQueued::dispatch($job);

        return $job;
    }

    public function complete(IntelligenceProcessingJob $job): IntelligenceProcessingJob
    {
        $job->update(['status' => 'completed', 'completed_at' => now()]);
        IntelligenceProcessingJobCompleted::dispatch($job);

        return $job;
    }

    public function fail(IntelligenceProcessingJob $job, string $message): IntelligenceProcessingJob
    {
        $job->update(['status' => 'failed', 'failed_at' => now(), 'error_summary' => $message]);
        IntelligenceProcessingJobFailed::dispatch($job);

        return $job;
    }
}
