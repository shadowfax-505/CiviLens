<?php

namespace App\Data\Ingestion;

final readonly class CrawlCursor
{
    /** @param array<string, mixed> $state */
    public function __construct(public array $state = []) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->state;
    }

    /** @return array<string, string> */
    public function conditionalHeaders(): array
    {
        return array_filter([
            'If-None-Match' => $this->safeHeader('etag'),
            'If-Modified-Since' => $this->safeHeader('last_modified'),
        ], fn (?string $value): bool => $value !== null);
    }

    private function safeHeader(string $key): ?string
    {
        $value = $this->state[$key] ?? null;

        return is_string($value)
            && $value !== ''
            && strlen($value) <= 500
            && preg_match('/[\x00-\x1F\x7F]/', $value) !== 1
                ? $value
                : null;
    }
}
