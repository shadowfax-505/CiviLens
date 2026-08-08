<?php

namespace App\Services\Intelligence;

use App\Data\Intelligence\PeerComparison;
use App\Data\Intelligence\PeerObservation;

/**
 * Position a procurement's value against comparable procurements.
 *
 * WHAT THIS IS NOT. A value far from its peers is not evidence of overpricing,
 * and this service never says it is. Legitimate variance is abundant in public
 * works: terrain, haulage distance, seasonal timing, and scope carried in a
 * description field rather than a structured one all move a price without
 * anything being wrong. The output states a measured difference and names the
 * peers it was measured against, so a reader -- or the procuring entity
 * responding to it -- can check the comparison rather than argue with a verdict.
 *
 * Robust statistics throughout. Median and median absolute deviation, never mean
 * and standard deviation: a cohort containing one genuine outlier would let that
 * outlier define the baseline it is being judged against.
 */
class PeerComparisonService
{
    /**
     * Scaled so that for normally distributed values the robust deviation is
     * comparable to a standard score.
     */
    private const MAD_SCALE = 1.4826;

    /**
     * @param  list<PeerObservation>  $candidates
     */
    public function compare(PeerObservation $subject, array $candidates): PeerComparison
    {
        $minimum = max(2, (int) config('civiclens.intelligence.minimum_peer_cohort', 8));

        $cohort = array_values(array_filter(
            $candidates,
            fn (PeerObservation $c): bool => $c->cohortKey() === $subject->cohortKey()
                && $c->reference !== $subject->reference
                && $c->value > 0.0,
        ));

        if (count($cohort) < $minimum) {
            return new PeerComparison(
                $subject->reference,
                $subject->cohortKey(),
                count($cohort),
                $minimum,
                $subject->value,
                null,
                null,
                null,
                array_map(fn (PeerObservation $c): string => $c->reference, $cohort),
                'Not compared: '.count($cohort).' comparable procurements found, fewer than the '.$minimum.' required. A difference measured against too few peers is not informative.',
            );
        }

        $values = array_map(fn (PeerObservation $c): float => $c->value, $cohort);
        $median = $this->median($values);
        $mad = $this->median(array_map(fn (float $v): float => abs($v - $median), $values));

        $ratio = $median > 0.0 ? round($subject->value / $median, 4) : null;
        $deviation = $mad > 0.0
            ? round(($subject->value - $median) / ($mad * self::MAD_SCALE), 4)
            : null;

        return new PeerComparison(
            $subject->reference,
            $subject->cohortKey(),
            count($cohort),
            $minimum,
            $subject->value,
            round($median, 4),
            $ratio,
            $deviation,
            array_map(fn (PeerObservation $c): string => $c->reference, $cohort),
            $this->statement($ratio, count($cohort)),
        );
    }

    /**
     * Neutral, checkable, and never a conclusion about conduct.
     */
    private function statement(?float $ratio, int $cohortSize): string
    {
        if ($ratio === null) {
            return 'Comparable procurements were found but their median value is zero, so no ratio can be stated.';
        }

        $direction = match (true) {
            $ratio >= 1.05 => 'higher than',
            $ratio <= 0.95 => 'lower than',
            default => 'close to',
        };

        return 'This value is '.number_format($ratio, 2).'x the median of '.$cohortSize
            .' comparable procurements, which is '.$direction
            .' the peer median. A difference from peers is not evidence of overpricing; scope, terrain, timing, and materials all vary legitimately.';
    }

    /** @param list<float> $values */
    private function median(array $values): float
    {
        sort($values);
        $count = count($values);

        if ($count === 0) {
            return 0.0;
        }

        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? $values[$middle]
            : ($values[$middle - 1] + $values[$middle]) / 2;
    }
}
