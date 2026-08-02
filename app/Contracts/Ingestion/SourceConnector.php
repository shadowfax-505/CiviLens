<?php

namespace App\Contracts\Ingestion;

use App\Data\Ingestion\CrawlCursor;
use App\Data\Ingestion\DiscoveryBatch;
use App\Models\SourceEndpoint;

interface SourceConnector
{
    public function discover(SourceEndpoint $endpoint, CrawlCursor $cursor): DiscoveryBatch;
}
