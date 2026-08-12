<?php

namespace App\Services\Extraction;

use App\Data\Extraction\ExtractedPage;

class PageRoutingPolicy
{
    public function __construct(private readonly BengaliTextPlausibility $plausibility) {}

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

        if ($page->density() < $threshold) {
            return self::OCR_REQUIRED;
        }

        // A dense text layer is not the same as a correct one. Legacy Bengali
        // fonts mapped as Unicode produce a full page of plausible-looking text
        // that decodes to the wrong characters, and density cannot see the
        // difference. Such a page is treated as having no usable text layer,
        // because OCR reads the glyphs that were actually rendered.
        if ($this->plausibility->isImplausible($page->text)) {
            return self::OCR_REQUIRED;
        }

        return self::NATIVE;
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
