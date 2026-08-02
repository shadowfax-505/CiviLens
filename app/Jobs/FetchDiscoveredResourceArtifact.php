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

    public int $tries = 4;

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
        return [(new RateLimited('source-fetch'))->releaseAfter(15)];
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
