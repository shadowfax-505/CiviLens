<?php

namespace App\Jobs;

use App\Models\DiscoveredResource;
use App\Models\SourceCrawlRun;
use App\Models\SourceEndpoint;
use App\Services\Ingestion\SourceAcquisitionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Throwable;

class FetchDiscoveredResourceArtifact implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Attempts, not failures.
     *
     * The rate limiter releases a job back to the queue when the endpoint's
     * per-minute budget is spent, and a release counts as an attempt. A page of
     * ten notices against a six-per-minute budget therefore burns four attempts
     * on waiting alone, and every artifact after the sixth failed with
     * MaxAttemptsExceeded without a single request being made. Waiting for a
     * rate limit is the system working, so the ceiling has to be high enough to
     * outlast it.
     *
     * The number follows from the budget rather than from taste: a backlog of
     * sixty artifacts at six per minute takes ten minutes to drain, and a job
     * released every thirty seconds spends twenty attempts waiting its turn. Two
     * hundred leaves room for a backlog several times that without ever
     * mistaking a queue for a fault.
     */
    public int $tries = 200;

    /**
     * Genuine errors still fail fast. Three thrown exceptions stop the job
     * regardless of how many attempts remain, so a permanently broken resource
     * does not retry forty times.
     */
    public int $maxExceptions = 3;

    public int $uniqueFor = 1800;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(
        public readonly int $resourceId,
        public readonly int $crawlRunId,
        public readonly int $endpointId,
    ) {
        $this->onQueue('ingestion');
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [(new RateLimited('source-fetch'))->releaseAfter(30)];
    }

    public function handle(SourceAcquisitionService $acquisition): void
    {
        $resource = DiscoveredResource::query()->findOrFail($this->resourceId);
        $run = SourceCrawlRun::query()->findOrFail($this->crawlRunId);
        $endpoint = SourceEndpoint::query()->findOrFail($this->endpointId);

        if ($endpoint->isPaused()) {
            $resource->update(['status' => 'paused']);
            $acquisition->recordFetchFailure($run, 'Source endpoint was paused before artifact retrieval.');

            return;
        }

        $acquisition->acquire($resource, $run);
    }

    public function failed(Throwable $exception): void
    {
        $run = SourceCrawlRun::query()->find($this->crawlRunId);

        if ($run instanceof SourceCrawlRun) {
            app(SourceAcquisitionService::class)->recordFetchFailure($run, $exception->getMessage());
        }
    }

    public function uniqueId(): string
    {
        return 'source-resource:'.$this->resourceId.':run:'.$this->crawlRunId;
    }
}
