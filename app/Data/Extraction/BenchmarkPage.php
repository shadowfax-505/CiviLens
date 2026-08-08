<?php

namespace App\Data\Extraction;

final readonly class BenchmarkPage
{
    /** @param array<string, string> $goldFields */
    public function __construct(
        public string $imagePath,
        public string $publisherGroup,
        public string $scriptClass,
        public string $split,
        public array $goldFields,
        public int $pageNumber,
    ) {}
}
