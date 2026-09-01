<?php

namespace App\Contracts\Extraction;

use App\Data\Extraction\OcrPageResult;
use App\Exceptions\Extraction\ExtractionFailed;

interface OcrEngine
{
    /** @throws ExtractionFailed */
    public function recognize(string $imagePath, int $dpi, int $pageSegmentationMode): OcrPageResult;

    /**
     * How far this page is turned, in degrees clockwise, or null if unknown.
     *
     * Null means "cannot tell", not "upright". An engine with no orientation
     * detection returns null for everything and the pipeline reads pages as
     * stored, which is the behaviour that existed before this was added.
     */
    public function detectRotation(string $imagePath, float $minimumConfidence = 1.5): ?int;
}
