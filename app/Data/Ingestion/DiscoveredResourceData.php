<?php

namespace App\Data\Ingestion;

use Carbon\CarbonImmutable;

final readonly class DiscoveredResourceData
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $canonicalUrl,
        public ?string $discoveryUrl = null,
        public ?string $externalId = null,
        public string $resourceType = 'document',
        public ?CarbonImmutable $publishedAt = null,
        public array $metadata = [],
    ) {}
}
