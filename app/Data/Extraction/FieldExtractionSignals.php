<?php

namespace App\Data\Extraction;

/**
 * Structural evidence about how a field value was located.
 *
 * Born-digital extraction has no recognizer and therefore no confidence: the
 * characters are exact, so a confidence-derived score is constant across every
 * field and ranks nothing. These are the signals that remain, and an ordering
 * built from them is what makes conformal calibration possible at all.
 *
 * Nothing here consults the gold value. A signal derived from the answer would
 * separate outcomes perfectly and certify by accident.
 */
final readonly class FieldExtractionSignals
{
    public function __construct(
        public bool $labelFoundExactly,
        public int $labelWordSpan,
        public float $gapInLabelHeights,
        public int $valueWordCount,
        public bool $valueMatchesExpectedFormat,
        public int $competingLabelsOnLine,
    ) {}

    /** @return array<string, float|int|bool> */
    public function toArray(): array
    {
        return [
            'label_found_exactly' => $this->labelFoundExactly,
            'label_word_span' => $this->labelWordSpan,
            'gap_in_label_heights' => round($this->gapInLabelHeights, 4),
            'value_word_count' => $this->valueWordCount,
            'value_matches_expected_format' => $this->valueMatchesExpectedFormat,
            'competing_labels_on_line' => $this->competingLabelsOnLine,
        ];
    }
}
