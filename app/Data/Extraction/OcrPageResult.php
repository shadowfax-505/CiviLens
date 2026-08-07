<?php

namespace App\Data\Extraction;

final readonly class OcrPageResult
{
    public function __construct(
        public string $text,
        public ?float $meanConfidence,
        public int $wordCount,
        public string $engine,
        public string $engineVersion,
        public string $languages,
        public int $dpi,
        public int $durationMs,
    ) {}

    /**
     * A page that produced no recognized words has no confidence to report.
     *
     * Returning null rather than 0.0 keeps "the engine was certain it saw
     * nothing" distinguishable from "the engine saw something and doubted it".
     * Only the second is a calibration signal.
     */
    public function isConfident(float $threshold): bool
    {
        return $this->meanConfidence !== null && $this->meanConfidence >= $threshold;
    }
}
