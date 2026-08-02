<?php

namespace App\Services\Ingestion;

use App\Models\SourceActivity;
use App\Models\SourceArtifactVersion;
use App\Models\SourceCrawlRun;
use App\Models\SourceEndpoint;
use App\Models\SourcePublisher;
use App\Models\User;

class SourceActivityRecorder
{
    /** @param array<string, mixed> $metadata */
    public function record(
        string $event,
        ?User $actor = null,
        ?SourcePublisher $publisher = null,
        ?SourceEndpoint $endpoint = null,
        ?SourceCrawlRun $run = null,
        ?SourceArtifactVersion $artifact = null,
        array $metadata = [],
    ): SourceActivity {
        $publisherId = $publisher instanceof SourcePublisher
            ? $publisher->id
            : ($endpoint instanceof SourceEndpoint ? $endpoint->source_publisher_id : null);
        $endpointId = $endpoint instanceof SourceEndpoint
            ? $endpoint->id
            : ($run instanceof SourceCrawlRun ? $run->source_endpoint_id : null);

        return SourceActivity::query()->create([
            'source_publisher_id' => $publisherId,
            'source_endpoint_id' => $endpointId,
            'source_crawl_run_id' => $run?->id,
            'source_artifact_version_id' => $artifact?->id,
            'actor_id' => $actor?->id,
            'event' => $event,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
