<?php

namespace App\Data\Ingestion;

final readonly class SafeHttpResponse
{
    /** @param array<string, list<string>> $headers */
    public function __construct(
        public string $url,
        public string $content,
        public string $mediaType,
        public array $headers,
        public int $status,
    ) {}

    public function notModified(): bool
    {
        return $this->status === 304;
    }
}
