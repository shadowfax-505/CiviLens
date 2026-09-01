<?php

namespace App\Services\Extraction;

use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Models\SourceArtifactVersion;
use App\Support\BengaliNumerals;
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
    /**
     * Which reader produced a score. **Bump this whenever a change alters what
     * a score means** — the locator's matching rules, the crop, the
     * nonconformity formula, the deferral rules.
     *
     * A score is a claim that two readings of the same pixels agreed, and it
     * stays on the row long after the reader that made it is gone. Without a
     * version there is nothing to compare, so a corpus half-scored by an old
     * reader and half by a new one looks exactly like a corpus scored once —
     * and the way this was actually found was by sampling values marked
     * unlocatable and noticing the locator could now find them.
     *
     * `civiclens:health` reports every version present. More than one means a
     * re-score is owed, and ADR-019 requires recalibration after it.
     *
     * History:
     *   r1 — the reader before this column existed; recorded as null.
     *   r2 — currency terminators, cell-aware cropping, sign-disagreement
     *        deferral, shared Bengali numeral canonicalisation.
     *   r3 — a numeral mixing Bengali and Latin digits defers by rule.
     *   r4 — readings that disagree about the leading digit defer by rule.
     */
    public const READER_VERSION = 'r4';

    /**
     * Every reason a value can carry instead of, or alongside, a score.
     *
     * Listed so a test can require each one to be written down in the method
     * card. A deferral is part of what the guarantee covers: the bound holds
     * over what is accepted, and every rule here shrinks that set. Adding one
     * silently would narrow the claim without narrowing the claim's
     * description, which is the failure this project keeps finding in itself.
     */
    public const SCORE_BASES = [
        'second-read',
        'unlocatable',
        'unreadable',
        'sign-disagreement',
        'mixed-script-numeral',
        'leading-digit-disputed',
    ];

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
        private readonly BengaliNumerals $numerals,
        private readonly TokenConfidence $tokenConfidence,
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

            // Recorded with the score, so the stratum stays selectable as the
            // corpus grows rather than needing a backfill each time.
            $field->forceFill($result + [
                'score_reader_version' => self::READER_VERSION,
                'token_confidence' => $this->tokenConfidence->for($field, $page),
            ])->save();

            $result['score_basis'] === 'unlocatable' ? $unlocatable++ : $scored++;
        }

        if ($image !== null) {
            $this->rasterizer->discard($image);
        }

        $this->workspace->discard($materialized);

        return ['scored' => $scored, 'unlocatable' => $unlocatable];
    }

    /**
     * Do the two readings disagree about whether the figure is negative?
     *
     * Only about the presence of a sign, never about which sign: a minus and a
     * Unicode minus are the same claim written two ways, and normalising them
     * apart would fire on every page that uses the typographic form.
     */
    /**
     * Whether the two readings disagree about the most significant digit.
     *
     * Two shapes, both of which change the order of magnitude:
     *
     *   substituted   ১০০০০ against ৯০০০০      — ten thousand or ninety
     *   inserted      39656.49 against 639656.49 — a sixteenfold difference
     *
     * The differing character must be a digit in at least one reading. A second
     * read that returns “3500.00 for 3500.00 has picked up a quotation mark off
     * the page, which is noise about the crop and not a dispute about the
     * number.
     */
    private function leadingDigitDisputed(string $first, string $second): bool
    {
        $first = trim($first);
        $second = trim($this->withoutTerminator($second));

        if ($first === '' || $second === '' || $first === $second) {
            return false;
        }

        $lengths = [mb_strlen($first), mb_strlen($second)];

        // Substituted: same length, same tail, different leading character.
        if ($lengths[0] === $lengths[1] && mb_substr($first, 1) === mb_substr($second, 1)) {
            return $this->isDigit(mb_substr($first, 0, 1)) || $this->isDigit(mb_substr($second, 0, 1));
        }

        // Inserted: one reading carries an extra leading character.
        if (abs($lengths[0] - $lengths[1]) !== 1) {
            return false;
        }

        [$longer, $shorter] = $lengths[0] > $lengths[1] ? [$first, $second] : [$second, $first];

        return mb_substr($longer, 1) === $shorter && $this->isDigit(mb_substr($longer, 0, 1));
    }

    private function isDigit(string $character): bool
    {
        return preg_match('/^[0-9\x{09E6}-\x{09EF}]$/u', $character) === 1;
    }

    /** The second read may carry a currency terminator the value never does. */
    private function withoutTerminator(string $value): string
    {
        return (string) preg_replace('#(/-|/=|:-|/)$#u', '', trim($value));
    }

    /**
     * Whether one numeral is written in two scripts at once.
     *
     * Only digits count. A value may legitimately carry Latin punctuation, a
     * currency mark or a unit beside Bengali digits; what no document does is
     * spell a single number half in one alphabet and half in the other.
     */
    private function mixesScripts(string $value): bool
    {
        return preg_match('/[\x{09E6}-\x{09EF}]/u', $value) === 1
            && preg_match('/[0-9]/', $value) === 1;
    }

    private function signsDisagree(string $first, string $second): bool
    {
        return $this->negative($first) !== $this->negative($second);
    }

    private function negative(string $text): bool
    {
        $trimmed = ltrim(trim($text), '(');

        return $trimmed !== '' && in_array(mb_substr($trimmed, 0, 1), ['-', "\u{2212}"], true);
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

        // A sign is not a character. Edit distance called ১৯৩৭৮ against
        // -১৯৩৭৮ a one-in-six difference and scored it 0.156, so a figure the
        // two readings disagree about the *meaning* of was certified — and
        // published as a recovery where the page showed a loss.
        //
        // Deferred by rule rather than by score. Scoring it 1.0 was the first
        // attempt and did nothing: the `bn` group's threshold is itself 1.0,
        // because its measured error rate sits inside α and conformal correctly
        // asks for no threshold at all there. A score can only express "worse
        // than most" and this has to express "not at any level".
        //
        // The bound is untouched, by the same argument as an unlocatable value
        // (ADR-019): a field that is never accepted cannot contribute to
        // P(accepted AND wrong).
        // One numeral, two alphabets. A document writes a number in a script;
        // it does not write ৫8,8৪8 or 1১৪০৭৭.৩৩. Every one of these is a
        // reading artefact, and the ones that certified were not harmless:
        //
        //   1১৪০৭৭.৩৩  second read [১৪০৭৭.৩৩  — a table rule line taken for a
        //                                       leading digit by one pass and a
        //                                       bracket by the other. The
        //                                       certified figure is ten times
        //                                       the one on the page.
        //   8৫১৭.৪     second read ৪৫১৭.৪     — the passes disagree about the
        //                                       first digit: Latin 8 against
        //                                       Bengali ৪, which is 4.
        //
        // Both scored below their group's threshold and were published. Edit
        // distance cannot see this: the strings differ in one character, and
        // that character is the most significant digit.
        //
        // Deferred, never corrected. Guessing which script was meant would
        // invent a figure, and this project has already published money once by
        // repairing a value instead of declining it.
        // The two readings disagree about the most significant digit.
        //
        // This is the disagreement that matters most and the one edit distance
        // weighs least. ১০০০০ against ৯০০০০ is ten thousand against ninety
        // thousand and scores one character in five; 39656.49 against
        // 639656.49 is a sixteenfold difference and scores one in nine. Both
        // certified.
        //
        // The magnitude is what a public-finance figure is *for*. A reading
        // that agrees about every digit except which order of magnitude the
        // number is has not agreed about anything worth publishing.
        //
        // Deferred rather than resolved: which reading is right is exactly what
        // neither pass can tell us.
        if ($this->leadingDigitDisputed((string) $field->extracted_value, $text)) {
            return [
                'nonconformity_score' => null,
                'score_basis' => 'leading-digit-disputed',
                'second_read_value' => mb_substr(trim($text), 0, 255),
                'second_read_confidence' => round($confidence, 4),
            ];
        }

        if ($this->mixesScripts((string) $field->extracted_value)) {
            return [
                'nonconformity_score' => null,
                'score_basis' => 'mixed-script-numeral',
                'second_read_value' => mb_substr(trim($text), 0, 255),
                'second_read_confidence' => round($confidence, 4),
            ];
        }

        if ($this->signsDisagree((string) $field->extracted_value, $text)) {
            return [
                'nonconformity_score' => null,
                'score_basis' => 'sign-disagreement',
                'second_read_value' => mb_substr(trim($text), 0, 255),
                'second_read_confidence' => round($confidence, 4),
            ];
        }

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
        // One definition, shared with search. Two copies of a rule about which
        // characters two readings must share is two chances for them to drift.
        return $this->numerals->canonical($text);
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
