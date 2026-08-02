<?php

namespace App\Data\Ingestion;

final readonly class DiscoveryBatch
{
    /** @param list<DiscoveredResourceData> $resources */
    public function __construct(
        public array $resources,
        public CrawlCursor $nextCursor,
    ) {}
}
