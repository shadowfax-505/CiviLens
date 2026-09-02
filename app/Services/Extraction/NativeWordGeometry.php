<?php

namespace App\Services\Extraction;

use App\Exceptions\Extraction\ExtractionFailed;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Word boxes for a page that was never recognised.
 *
 * A value is located by matching a run of words against their boxes, and boxes
 * came only from OCR. A page read from its own text layer therefore had text
 * and no geometry, so every value on it was unlocatable and deferred by rule:
 * 78,551 of 79,226 court candidates, because 31,064 of 31,795 court pages are
 * native.
 *
 * The remedy already in the codebase re-recognises the page to recover boxes,
 * which for that corpus is about thirteen hours. But a PDF with a text layer
 * already knows where its words are — `pdftotext -bbox` reports it in about
 * ten milliseconds a page, and the answer is better than OCR's because it is
 * the position the document declares rather than a position inferred from an
 * image of it.
 */
class NativeWordGeometry
{
    /** PDF user space is 72 units to the inch; stored boxes are pixels. */
    private const POINTS_PER_INCH = 72.0;

    /**
     * Word boxes for one page, in pixels at the given resolution.
     *
     * The resolution is a choice, not a measurement — a text layer has no
     * pixels — so it is recorded on the page alongside the boxes, exactly as
     * a recognised page records the DPI it was read at. Anything drawing a
     * crop later rasterises to the same number or draws in the wrong place.
     *
     * @return list<array{t: string, c: float, l: int, y: int, w: int, h: int}>
     */
    public function forPage(string $pdfPath, int $pageNumber, int $dpi): array
    {
        $process = new Process([
            (string) config('civiclens.extraction.pdftotext_binary', 'pdftotext'),
            '-bbox',
            '-f', (string) $pageNumber,
            '-l', (string) $pageNumber,
            $pdfPath,
            '-',
        ]);

        $process->setTimeout((float) config('civiclens.extraction.ocr.timeout_seconds', 120));

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            throw new ExtractionFailed('Reading word positions exceeded the configured time limit.');
        }

        if (! $process->isSuccessful()) {
            throw new ExtractionFailed('Word positions could not be read from the document.');
        }

        $matched = preg_match_all(
            '#<word xMin="([\d.]+)" yMin="([\d.]+)" xMax="([\d.]+)" yMax="([\d.]+)">([^<]*)</word>#',
            $process->getOutput(),
            $words,
            PREG_SET_ORDER,
        );

        if ($matched === false || $matched === 0) {
            return [];
        }

        $scale = $dpi / self::POINTS_PER_INCH;
        $boxes = [];

        foreach ($words as $word) {
            $text = html_entity_decode($word[5], ENT_QUOTES | ENT_XML1, 'UTF-8');

            if (trim($text) === '') {
                continue;
            }

            $boxes[] = [
                't' => $text,
                // The document states where its words are; it does not guess.
                // Recorded as full confidence so the token-confidence gate
                // treats a declared position as what it is, rather than as a
                // recognition nobody vouched for.
                'c' => 100.0,
                'l' => (int) round(((float) $word[1]) * $scale),
                'y' => (int) round(((float) $word[2]) * $scale),
                'w' => (int) round((((float) $word[3]) - ((float) $word[1])) * $scale),
                'h' => (int) round((((float) $word[4]) - ((float) $word[2])) * $scale),
            ];
        }

        return $boxes;
    }
}
