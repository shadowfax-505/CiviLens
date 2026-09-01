<?php

namespace App\Services\Extraction;

use App\Models\ExtractionPage;
use GdImage;

/**
 * Put a rendered page back into the frame its word boxes were measured in.
 *
 * A sideways page is straightened before it is read, so its geometry is
 * recorded in the *rotated* frame. Anything that renders the page afterwards
 * gets the stored orientation, and a box drawn on it lands somewhere else
 * entirely — the reader is shown a confident red rectangle around the wrong
 * part of the page, which is worse than showing nothing.
 *
 * This is the same failure as rendering at the wrong DPI, which put boxes off
 * the raster and produced a crop one pixel wide on 24 pages, four of which had
 * already been adjudicated from that screen. Geometry is only meaningful
 * alongside the frame it was measured in, and the frame is now two numbers:
 * the DPI and the turn.
 */
class PageFrame
{
    /**
     * Rewrite the image at $path in the page's recognition frame.
     *
     * Does nothing when the page was read as stored, which is every page read
     * before straightening existed, and nothing when the page cannot be
     * resolved — an unknown frame is not a reason to invent a turn.
     */
    public function align(string $path, ?ExtractionPage $page): void
    {
        $degrees = $page?->rotation_applied;

        if ($degrees === null || $degrees % 360 === 0) {
            return;
        }

        $source = @imagecreatefrompng($path);

        if (! $source instanceof GdImage) {
            return;
        }

        // The same turn the recognizer applied, by the same sign convention:
        // GD rotates anticlockwise, Tesseract reports the clockwise turn.
        $rotated = @imagerotate($source, -$degrees, 0);

        if ($rotated instanceof GdImage) {
            @imagepng($rotated, $path);
        }
    }
}
