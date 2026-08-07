<?php

namespace App\Services\Extraction;

use App\Contracts\Extraction\NativeTextExtractor;
use App\Exceptions\Extraction\ExtractionFailed;

class NativeExtractorRegistry
{
    /** @param list<NativeTextExtractor> $extractors */
    public function __construct(private readonly array $extractors) {}

    public function for(string $mediaType): NativeTextExtractor
    {
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($mediaType)) {
                return $extractor;
            }
        }

        throw new ExtractionFailed('No native extractor is registered for this media type.');
    }

    public function supports(string $mediaType): bool
    {
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($mediaType)) {
                return true;
            }
        }

        return false;
    }
}
