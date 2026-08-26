<?php

namespace App\Services\Statistics;

use InvalidArgumentException;

/**
 * An exact confidence interval for a proportion.
 *
 * The realized risk of this system is a handful of errors out of a few hundred
 * labels. A point estimate quoted from that — "2.4% of accepted values were
 * wrong" — carries an air of precision the sample cannot support: with twelve
 * errors, the difference between 1% and 4% is a couple of judgements going the
 * other way, and a reader has no way to see that from the number alone.
 *
 * Clopper–Pearson rather than a normal approximation. The counts here are small
 * and the proportions near zero, which is exactly where a Wald interval reaches
 * below zero and reports a lower bound that cannot happen.
 */
class ClopperPearson
{
    /**
     * @return array{low: float, high: float}
     */
    public function interval(int $successes, int $trials, float $confidence = 0.95): array
    {
        if ($trials < 0 || $successes < 0 || $successes > $trials) {
            throw new InvalidArgumentException('A count of successes must lie between zero and the number of trials.');
        }

        if ($trials === 0) {
            // Nothing was observed, so the interval is the whole range. Any
            // narrower claim would be invented.
            return ['low' => 0.0, 'high' => 1.0];
        }

        $alpha = 1.0 - $confidence;

        return [
            'low' => $successes === 0
                ? 0.0
                : round($this->inverseBeta($alpha / 2, $successes, $trials - $successes + 1), 6),
            'high' => $successes === $trials
                ? 1.0
                : round($this->inverseBeta(1 - ($alpha / 2), $successes + 1, $trials - $successes), 6),
        ];
    }

    /**
     * The value x where the regularised incomplete beta reaches $target.
     *
     * By bisection. The function is monotonic in x, the domain is [0,1], and
     * fifty halvings put the answer well inside the rounding this reports at —
     * a Newton step would be faster and would need a derivative to be right
     * about, which is not worth it for a report that runs once.
     */
    private function inverseBeta(float $target, float $a, float $b): float
    {
        $low = 0.0;
        $high = 1.0;

        for ($step = 0; $step < 60; $step++) {
            $mid = ($low + $high) / 2;

            if ($this->regularisedIncompleteBeta($mid, $a, $b) < $target) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        return ($low + $high) / 2;
    }

    /**
     * The regularised incomplete beta function, by its continued fraction.
     *
     * Numerical Recipes' formulation. The fraction converges quickly for
     * x < (a+1)/(a+b+2) and the symmetry I(x;a,b) = 1 − I(1−x;b,a) covers the
     * rest, which is what keeps this accurate near both ends of the range.
     */
    private function regularisedIncompleteBeta(float $x, float $a, float $b): float
    {
        if ($x <= 0.0) {
            return 0.0;
        }

        if ($x >= 1.0) {
            return 1.0;
        }

        $front = exp(
            $this->logGamma($a + $b) - $this->logGamma($a) - $this->logGamma($b)
            + ($a * log($x)) + ($b * log(1.0 - $x))
        );

        if ($x < ($a + 1.0) / ($a + $b + 2.0)) {
            return $front * $this->continuedFraction($x, $a, $b) / $a;
        }

        return 1.0 - ($front * $this->continuedFraction(1.0 - $x, $b, $a) / $b);
    }

    private function continuedFraction(float $x, float $a, float $b): float
    {
        $tiny = 1e-30;
        $c = 1.0;
        $d = 1.0 - (($a + $b) * $x / ($a + 1.0));
        $d = abs($d) < $tiny ? $tiny : 1.0 / $d;
        $result = $d;

        for ($m = 1; $m <= 300; $m++) {
            $even = 2 * $m;

            $numerator = $m * ($b - $m) * $x / (($a + $even - 1.0) * ($a + $even));
            $d = 1.0 + ($numerator * $d);
            $d = abs($d) < $tiny ? $tiny : 1.0 / $d;
            $c = 1.0 + ($numerator / $c);
            $c = abs($c) < $tiny ? $tiny : $c;
            $result *= $d * $c;

            $numerator = -($a + $m) * ($a + $b + $m) * $x / (($a + $even) * ($a + $even + 1.0));
            $d = 1.0 + ($numerator * $d);
            $d = abs($d) < $tiny ? $tiny : 1.0 / $d;
            $c = 1.0 + ($numerator / $c);
            $c = abs($c) < $tiny ? $tiny : $c;
            $step = $d * $c;
            $result *= $step;

            if (abs($step - 1.0) < 1e-12) {
                break;
            }
        }

        return $result;
    }

    /** Lanczos, which is accurate to more places than anything here reports. */
    private function logGamma(float $x): float
    {
        $coefficients = [
            676.5203681218851, -1259.1392167224028, 771.32342877765313,
            -176.61502916214059, 12.507343278686905, -0.13857109526572012,
            9.9843695780195716e-6, 1.5056327351493116e-7,
        ];

        if ($x < 0.5) {
            return log(M_PI / sin(M_PI * $x)) - $this->logGamma(1.0 - $x);
        }

        $x -= 1.0;
        $a = 0.99999999999980993;
        $t = $x + 7.5;

        foreach ($coefficients as $index => $coefficient) {
            $a += $coefficient / ($x + $index + 1);
        }

        return (0.5 * log(2 * M_PI)) + (($x + 0.5) * log($t)) - $t + log($a);
    }
}
