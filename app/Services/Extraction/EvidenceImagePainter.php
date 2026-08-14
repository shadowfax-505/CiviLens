<?php

namespace App\Services\Extraction;

use RuntimeException;

/**
 * Mark and cut out the part of a page a value was read from.
 *
 * The full page answers "is this the right document"; only the marked region
 * answers "do these characters match", which is the question actually being put
 * to a reviewer.
 */
class EvidenceImagePainter
{
    /** Room around the value so its row and neighbours stay visible. */
    private const CROP_PADDING = 60;

    /** Smallest crop worth showing: a lone figure with no context is no easier. */
    private const MIN_CROP_WIDTH = 700;

    /**
     * @param  list<array{left: int, top: int, right: int, bottom: int}>  $boxes
     */
    public function highlight(string $png, array $boxes): string
    {
        $image = $this->read($png);
        $red = (int) imagecolorallocate($image, 220, 38, 38);

        foreach ($boxes as $box) {
            // Drawn outside the characters, never over them: a mark that covers
            // what is being judged defeats the point.
            for ($inset = 0; $inset < 3; $inset++) {
                imagerectangle(
                    $image,
                    $box['left'] - 4 - $inset,
                    $box['top'] - 4 - $inset,
                    $box['right'] + 4 + $inset,
                    $box['bottom'] + 4 + $inset,
                    $red,
                );
            }
        }

        return $this->render($image);
    }

    /**
     * @param  array{left: int, top: int, right: int, bottom: int}  $box
     */
    public function crop(string $png, array $box): string
    {
        $image = $this->read($png);
        $width = imagesx($image);
        $height = imagesy($image);

        $left = max(0, $box['left'] - self::CROP_PADDING);
        $top = max(0, $box['top'] - self::CROP_PADDING);
        $right = min($width, $box['right'] + self::CROP_PADDING);
        $bottom = min($height, $box['bottom'] + self::CROP_PADDING);

        // Widened around its centre rather than from the left edge, so the value
        // stays in the middle of what the reviewer sees.
        if ($right - $left < self::MIN_CROP_WIDTH) {
            $centre = (int) (($left + $right) / 2);
            $left = max(0, $centre - self::MIN_CROP_WIDTH / 2);
            $right = min($width, $left + self::MIN_CROP_WIDTH);
        }

        $cropped = imagecrop($image, [
            'x' => $left,
            'y' => $top,
            'width' => max(1, $right - $left),
            'height' => max(1, $bottom - $top),
        ]);

        if ($cropped === false) {
            return $this->render($image);
        }

        $red = (int) imagecolorallocate($cropped, 220, 38, 38);

        for ($inset = 0; $inset < 3; $inset++) {
            imagerectangle(
                $cropped,
                $box['left'] - $left - 4 - $inset,
                $box['top'] - $top - 4 - $inset,
                $box['right'] - $left + 4 + $inset,
                $box['bottom'] - $top + 4 + $inset,
                $red,
            );
        }

        return $this->render($cropped);
    }

    private function read(string $png): \GdImage
    {
        $image = imagecreatefromstring($png);

        if ($image === false) {
            throw new RuntimeException('The page image could not be read.');
        }

        return $image;
    }

    private function render(\GdImage $image): string
    {
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
