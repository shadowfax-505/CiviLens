<?php

namespace App\Services\Extraction;

use App\Models\ExtractionPage;
use App\Models\SourceArtifactVersion;
use GdImage;
use Throwable;

/**
 * Why a page was abstained on.
 *
 * "Confidence remained below the acceptance threshold" was recorded for every
 * abstention, including 198 pages on which the recognizer produced no
 * characters and no confidence, because the page is blank. Those are opposite
 * findings: one says the reader failed, the other says there was nothing to
 * read, and only the first is a limitation of this system.
 *
 * The evidence is the page itself. Ink is measured off the same raster the
 * recognizer saw, so a page reported blank is one that is blank at the
 * resolution the decision was made at rather than blank in principle.
 */
class AbstentionClassifier
{
    /**
     * Below this share of dark pixels there is nothing on the page to read.
     *
     * Measured against sampled pages of this corpus: genuinely empty pages
     * return 0.0000, and the sparsest page carrying a heading returns 0.0127.
     * The line sits below anything observed to carry content.
     */
    private const BLANK_INK = 0.002;

    /** Ink present, but little enough that a heading or a stamp is all there is. */
    private const SPARSE_INK = 0.02;

    /**
     * How close to the acceptance threshold counts as near.
     *
     * A page here was read, and read almost well enough. It is the band where a
     * third pass at a higher resolution is worth its cost, and the only band
     * where that is true.
     */
    private const NEAR_THRESHOLD = 5.0;

    public const BLANK = 'blank-page';

    public const SPARSE = 'almost-blank-page';

    public const NEAR_MISS = 'read-just-below-the-threshold';

    public const UNREADABLE = 'read-far-below-the-threshold';

    public const UNKNOWN = 'could-not-render-the-page';

    public function __construct(
        private readonly ArtifactWorkspace $workspace,
        private readonly PageRasterizer $rasterizer,
    ) {}

    /**
     * @return array<string, int>
     */
    public function classify(int $limit = 2000): array
    {
        $counts = [];

        ExtractionPage::query()
            ->where('extraction_path', SelectiveOcrService::ABSTAINED)
            ->whereNull('abstention_reason')
            ->with('run.artifactVersion')
            ->orderBy('id')
            ->limit($limit)
            ->chunkById(50, function ($pages) use (&$counts): void {
                foreach ($pages as $page) {
                    $ink = $this->inkCoverage($page);
                    $reason = $this->reasonFor($page, $ink);

                    $page->forceFill([
                        'abstention_reason' => $reason,
                        'ink_coverage' => $ink,
                    ])->save();

                    $counts[$reason] = ($counts[$reason] ?? 0) + 1;
                }
            });

        arsort($counts);

        return $counts;
    }

    private function reasonFor(ExtractionPage $page, ?float $ink): string
    {
        if ($ink === null) {
            return self::UNKNOWN;
        }

        if ($ink < self::BLANK_INK) {
            return self::BLANK;
        }

        $threshold = (float) config('civiclens.extraction.ocr.accept_confidence', 80.0);
        $confidence = $page->confidence;

        // Ink on the page and no confidence recorded means the recognizer
        // returned nothing from something. That is a failure to read, not an
        // empty page, and it is reported as the worse of the two.
        if ($confidence === null) {
            return $ink < self::SPARSE_INK ? self::SPARSE : self::UNREADABLE;
        }

        return ((float) $confidence) >= $threshold - self::NEAR_THRESHOLD
            ? self::NEAR_MISS
            : self::UNREADABLE;
    }

    private function inkCoverage(ExtractionPage $page): ?float
    {
        $artifact = $page->run?->artifactVersion;

        if (! $artifact instanceof SourceArtifactVersion) {
            return null;
        }

        $materialized = null;
        $image = null;
        $raster = null;

        try {
            $materialized = $this->workspace->materialize($artifact);
            $image = $this->rasterizer->rasterize(
                $materialized,
                (int) $page->page_number,
                $page->recognized_dpi ?? (int) config('civiclens.extraction.ocr.primary_dpi', 150),
            );
            $raster = @imagecreatefrompng($image);

            if (! $raster instanceof GdImage) {
                return null;
            }

            return $this->darkShare($raster);
        } catch (Throwable) {
            return null;
        } finally {
            if ($image !== null) {
                $this->rasterizer->discard($image);
            }

            $this->workspace->discard($materialized);
        }
    }

    /**
     * Sampled every sixth pixel. A page is blank or it is not, and reading two
     * million pixels to decide that would make this too slow to run over a
     * corpus.
     */
    private function darkShare(GdImage $raster): float
    {
        $width = imagesx($raster);
        $height = imagesy($raster);
        $dark = 0;
        $total = 0;

        for ($y = 0; $y < $height; $y += 6) {
            for ($x = 0; $x < $width; $x += 6) {
                $colour = imagecolorat($raster, $x, $y);
                $luminance = (($colour >> 16 & 255) * 0.3) + (($colour >> 8 & 255) * 0.59) + (($colour & 255) * 0.11);
                $total++;

                if ($luminance < 200) {
                    $dark++;
                }
            }
        }

        // A raster always has pixels, so $total is never zero here; sampling
        // every sixth of them cannot empty a page that exists.
        return round($dark / $total, 6);
    }
}
