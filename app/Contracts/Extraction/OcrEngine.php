<?php

namespace App\Contracts\Extraction;

use App\Data\Extraction\OcrPageResult;
use App\Exceptions\Extraction\ExtractionFailed;

interface OcrEngine
{
    /** @throws ExtractionFailed */
    public function recognize(string $imagePath, int $dpi, int $pageSegmentationMode): OcrPageResult;
}
