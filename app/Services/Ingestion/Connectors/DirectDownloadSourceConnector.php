<?php

namespace App\Services\Ingestion\Connectors;

use App\Contracts\Ingestion\SourceConnector;
use App\Data\Ingestion\CrawlCursor;
use App\Data\Ingestion\DiscoveredResourceData;
use App\Data\Ingestion\DiscoveryBatch;
use App\Models\SourceEndpoint;
use App\Services\Ingestion\ApprovedSourceUrlGuard;

class DirectDownloadSourceConnector implements SourceConnector
{
    public function __construct(private readonly ApprovedSourceUrlGuard $urlGuard) {}

    public function discover(SourceEndpoint $endpoint, CrawlCursor $cursor): DiscoveryBatch
    {
        $validated = $this->urlGuard->validate($endpoint->base_url, $endpoint);

        return new DiscoveryBatch(
            [new DiscoveredResourceData($validated->url, $validated->url)],
            new CrawlCursor(['checked_at' => now()->toAtomString()]),
        );
    }
}
