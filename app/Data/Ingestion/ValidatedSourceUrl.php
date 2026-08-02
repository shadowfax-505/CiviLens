<?php

namespace App\Data\Ingestion;

final readonly class ValidatedSourceUrl
{
    public function __construct(
        public string $url,
        public string $host,
        public string $ipAddress,
    ) {}
}
