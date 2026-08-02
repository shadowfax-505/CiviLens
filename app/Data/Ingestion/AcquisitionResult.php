<?php

namespace App\Data\Ingestion;

final readonly class AcquisitionResult
{
    /** @param array<string, list<string>> $headers */
    public function __construct(
        public string $retrievalUrl,
        public string $content,
        public string $mediaType,
        public int $byteSize,
        public string $sha256,
        public array $headers,
        public MalwareScanResult $malwareScan,
    ) {}
}
