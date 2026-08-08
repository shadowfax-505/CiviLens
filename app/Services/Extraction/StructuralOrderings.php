<?php

namespace App\Services\Extraction;

use App\Data\Extraction\FieldExtractionSignals;

/**
 * Candidate orderings over extracted fields, for conformal calibration.
 *
 * Split conformal's threshold is a quantile of calibration scores, which is a
 * rank statistic, so the guarantee is invariant under any strictly monotone
 * transformation of the score. Only the ordering matters. That is why a
 * structural ordering suffices where born-digital extraction offers no
 * probability at all.
 *
 * Each ordering returns a value where LOWER means more conforming. The absolute
 * magnitudes are meaningless and must never be interpreted as probabilities --
 * only their order is used, and only their order is defensible.
 *
 * Which ordering ranks best is an empirical question with a real possible
 * answer of "none of them beat chance".
 */
class StructuralOrderings
{
    public const LABEL_EXACTNESS = 'label_exactness';

    public const LAYOUT_DISTANCE = 'layout_distance';

    public const FORMAT_CONFORMANCE = 'format_conformance';

    public const COMBINED = 'combined';

    /** @return list<string> */
    public function names(): array
    {
        return [self::LABEL_EXACTNESS, self::LAYOUT_DISTANCE, self::FORMAT_CONFORMANCE, self::COMBINED];
    }

    public function score(string $ordering, FieldExtractionSignals $signals): float
    {
        return match ($ordering) {
            self::LABEL_EXACTNESS => $this->labelExactness($signals),
            self::LAYOUT_DISTANCE => $this->layoutDistance($signals),
            self::FORMAT_CONFORMANCE => $this->formatConformance($signals),
            self::COMBINED => $this->combined($signals),
            default => 1.0,
        };
    }

    /**
     * A label matched as a single exact run is the strongest structural evidence
     * that the right field was found. A label reassembled across many words is
     * weaker, because the run may have crossed a column boundary.
     */
    private function labelExactness(FieldExtractionSignals $signals): float
    {
        if (! $signals->labelFoundExactly) {
            return 1.0;
        }

        return min(0.99, ($signals->labelWordSpan - 1) / 10);
    }

    /**
     * A value immediately after its label is more likely to belong to it. The
     * further right it sits, the more chance an unrelated column was crossed --
     * and a line carrying several labels makes that more likely still.
     */
    private function layoutDistance(FieldExtractionSignals $signals): float
    {
        if (! $signals->labelFoundExactly) {
            return 1.0;
        }

        $distance = min(1.0, max(0.0, $signals->gapInLabelHeights) / 20);
        $crowding = min(0.5, $signals->competingLabelsOnLine * 0.1);

        return min(0.99, $distance + $crowding);
    }

    /**
     * A value that parses as the type its field expects is more conforming.
     * This checks shape only, never correctness -- an amount that parses as a
     * number can still be the wrong number.
     */
    private function formatConformance(FieldExtractionSignals $signals): float
    {
        if (! $signals->labelFoundExactly || $signals->valueWordCount === 0) {
            return 1.0;
        }

        return $signals->valueMatchesExpectedFormat ? 0.1 : 0.8;
    }

    /**
     * Mean of the three. Deliberately unweighted: tuning weights against
     * outcomes would let the label leak into the score, which is the failure
     * this whole layer exists to avoid.
     */
    private function combined(FieldExtractionSignals $signals): float
    {
        $parts = [
            $this->labelExactness($signals),
            $this->layoutDistance($signals),
            $this->formatConformance($signals),
        ];

        return round(array_sum($parts) / count($parts), 6);
    }
}
