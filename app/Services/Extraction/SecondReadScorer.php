<?php

namespace App\Services\Extraction;

use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Models\SourceArtifactVersion;
use GdImage;
use Throwable;

/**
 * Score a field by reading it a second time and seeing whether the two agree.
 *
 * The score this replaces was the page's OCR confidence. It is identical for
 * every field on a page — 0 of 124 labelled pages showed any variation within
 * the page — and ranks errors at AUC 0.511, which is a coin flip. A bound over
 * such a score is still valid and still certifies an arbitrary selection.
 *
 * A second read of the value's own marked region is per-field by construction
 * and sees no label: it re-reads pixels. On the probe that motivated this, it
 * agreed with all 18 correct fields and disagreed with all 5 wrong ones, and the
 * disagreements were the failure modes already reported — ২,৯৬,১০৬ re-read as
 * ২৯৬,১০৬, and 889908 re-read as 885508.
 *
 * It is a second opinion, not ground truth. The same engine family can misread
 * the same glyph the same way twice and agree with itself, which bounds what
 * this can detect.
 */
class SecondReadScorer
{
    /** Room around the value, so a character at the edge is not clipped away. */
    private const PADDING = 8;

    /** Small crops read badly at their native size; this is enough to recover. */
    private const UPSCALE = 3;

    /** Single line: the crop is one value, not a page or a paragraph. */
    private const SINGLE_LINE_PSM = 7;

    public function __construct(
        private readonly ValueLocator $locator,
        private readonly ArtifactWorkspace $workspace,
        private readonly PageRasterizer $rasterizer,
        private readonly TesseractOcrEngine $engine,
        private readonly AmountGrouping $grouping,
    ) {}

    /**
     * Score every field on one page, rasterising the page once.
     *
     * Per page rather than per field: one raster serves every field on it, which
     * is the difference between forty minutes over the corpus and several hours.
     *
     * @return array{scored: int, unlocatable: int}
     */
    public function scorePage(ExtractionPage $page): array
    {
        $fields = ExtractionField::query()->where('extraction_page_id', $page->getKey())->get();

        if ($fields->isEmpty()) {
            return ['scored' => 0, 'unlocatable' => 0];
        }

        $artifact = $page->run?->artifactVersion;
        $image = null;
        $materialized = null;
        $raster = null;

        try {
            if ($artifact instanceof SourceArtifactVersion) {
                $materialized = $this->workspace->materialize($artifact);
                $image = $this->rasterizer->rasterize(
                    $materialized,
                    (int) $page->page_number,
                    // The DPI the boxes were measured at, or every crop lands in
                    // the wrong place.
                    $page->recognized_dpi ?? (int) config('civiclens.extraction.ocr.primary_dpi', 150),
                );
                $raster = @imagecreatefrompng($image);
            }
        } catch (Throwable) {
            $raster = null;
        }

        $scored = 0;
        $unlocatable = 0;

        foreach ($fields as $field) {
            $result = $this->score($field, $page, $raster instanceof GdImage ? $raster : null);

            $field->forceFill($result)->save();

            $result['score_basis'] === 'unlocatable' ? $unlocatable++ : $scored++;
        }

        if ($image !== null) {
            $this->rasterizer->discard($image);
        }

        $this->workspace->discard($materialized);

        return ['scored' => $scored, 'unlocatable' => $unlocatable];
    }

    /**
     * @return array{nonconformity_score: float|null, score_basis: string, second_read_value: string|null, second_read_confidence: float|null}
     */
    private function score(ExtractionField $field, ExtractionPage $page, ?GdImage $raster): array
    {
        $boxes = $raster instanceof GdImage ? $this->locator->locate($field, $page) : [];

        if ($boxes === []) {
            // Unknown, not wrong. Scoring it 1.0 said "as bad as the worst
            // misreading" about 57 fields that were read correctly and simply
            // could not be found again, which cost the whole score its
            // discrimination — 0.75 measured with them, 0.90 without.
            //
            // A null score defers by rule rather than by threshold, and a field
            // that is never accepted cannot contribute to P(accepted AND wrong),
            // so the joint bound over the fields the score does apply to is
            // untouched.
            return [
                'nonconformity_score' => null,
                'score_basis' => 'unlocatable',
                'second_read_value' => null,
                'second_read_confidence' => null,
            ];
        }

        try {
            $read = $this->reread($raster, $boxes[0]);
        } catch (Throwable) {
            return [
                'nonconformity_score' => null,
                'score_basis' => 'unreadable',
                'second_read_value' => null,
                'second_read_confidence' => null,
            ];
        }

        [$text, $confidence] = $read;

        $distance = $this->distance(
            $this->canonical((string) $field->extracted_value),
            $this->canonical($text),
        );

        // Agreement dominates. A barely legible agreement is weaker evidence than
        // a clear one, and grouping breaks the tie: a misplaced separator differs
        // by a single character and is a hundredfold error.
        $score = (0.6 * $distance)
            + (0.3 * (1.0 - (max(0.0, min(100.0, $confidence)) / 100)))
            + (0.1 * ($this->grouping->groupsCorrectly((string) $field->extracted_value) ? 0.0 : 1.0));

        return [
            'nonconformity_score' => round(max(0.0, min(1.0, $score)), 8),
            'score_basis' => 'second-read',
            'second_read_value' => mb_substr(trim($text), 0, 255),
            'second_read_confidence' => round($confidence, 4),
        ];
    }

    /**
     * @param  array{left: int, top: int, right: int, bottom: int}  $box
     * @return array{0: string, 1: float}
     */
    private function reread(GdImage $raster, array $box): array
    {
        $width = imagesx($raster);
        $height = imagesy($raster);

        $left = max(0, $box['left'] - self::PADDING);
        $top = max(0, $box['top'] - self::PADDING);
        $right = min($width, $box['right'] + self::PADDING);
        $bottom = min($height, $box['bottom'] + self::PADDING);

        $crop = imagecrop($raster, [
            'x' => $left,
            'y' => $top,
            'width' => max(1, $right - $left),
            'height' => max(1, $bottom - $top),
        ]);

        if ($crop === false) {
            throw new \RuntimeException('The value region could not be cut out.');
        }

        $scaled = imagescale($crop, imagesx($crop) * self::UPSCALE, imagesy($crop) * self::UPSCALE);
        $path = tempnam(sys_get_temp_dir(), 'civiclens-second-read').'.png';

        imagepng($scaled === false ? $crop : $scaled, $path);

        try {
            $result = $this->engine->recognize(
                $path,
                ((int) config('civiclens.extraction.ocr.primary_dpi', 150)) * self::UPSCALE,
                self::SINGLE_LINE_PSM,
            );

            return [$result->text, $result->meanConfidence ?? 0.0];
        } finally {
            @unlink($path);
        }
    }

    /**
     * The reading reduced to the characters both alphabets share.
     *
     * Legacy-font pages store Bengali numerals as the Latin bytes that render
     * them, so 216.9 and ২১৬.৯ are one reading written two ways. Treating that
     * as disagreement would fire on every native page, and the script difference
     * is already put to reviewers as its own question.
     */
    private function canonical(string $text): string
    {
        $text = str_replace(
            ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $text,
        );

        return (string) preg_replace('/[^0-9.,()\-]/u', '', $text);
    }

    /**
     * Edit distance as a fraction of the longer string, so a wrong digit in a
     * short figure counts for more than one in a long reference number.
     */
    private function distance(string $stored, string $read): float
    {
        if ($stored === '' && $read === '') {
            return 0.0;
        }

        $longest = max(mb_strlen($stored), mb_strlen($read));

        return $longest === 0 ? 1.0 : min(1.0, levenshtein($stored, $read) / $longest);
    }
}
