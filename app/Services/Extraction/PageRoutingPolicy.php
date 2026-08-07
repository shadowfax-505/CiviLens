<?php

namespace App\Services\Extraction;

use App\Data\Extraction\ExtractedPage;

class PageRoutingPolicy
{
    public const NATIVE = 'native';

    public const OCR_REQUIRED = 'ocr_required';

    /**
     * Decide whether a page's text layer is usable or whether it must go to OCR.
     *
     * The threshold is configuration, not a constant, because it is an operating
     * point that has to be reported and varied rather than assumed.
     */
    public function decide(ExtractedPage $page): string
    {
        $threshold = (float) config('civiclens.extraction.native_density_threshold', 1.5);

        return $page->density() >= $threshold ? self::NATIVE : self::OCR_REQUIRED;
    }

    /** @param list<string> $pagePaths */
    public function summarize(array $pagePaths): string
    {
        if ($pagePaths === []) {
            return 'empty';
        }

        $native = count(array_filter($pagePaths, fn (string $path): bool => $path === self::NATIVE));

        return match (true) {
            $native === count($pagePaths) => self::NATIVE,
            $native === 0 => self::OCR_REQUIRED,
            default => 'mixed',
        };
    }
}
