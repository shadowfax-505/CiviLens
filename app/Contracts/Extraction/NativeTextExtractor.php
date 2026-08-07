<?php

namespace App\Contracts\Extraction;

use App\Data\Extraction\NativeExtractionResult;
use App\Exceptions\Extraction\ExtractionFailed;

interface NativeTextExtractor
{
    public function supports(string $mediaType): bool;

    /** @throws ExtractionFailed */
    public function extract(string $absolutePath): NativeExtractionResult;
}
