<?php

namespace App\Data\Extraction;

final readonly class NativeExtractionResult
{
    /** @param list<ExtractedPage> $pages */
    public function __construct(
        public array $pages,
        public string $engine,
        public string $engineVersion,
        public int $durationMs,
    ) {}

    public function pageCount(): int
    {
        return count($this->pages);
    }
}
