<?php

namespace App\Services\Extraction;

use App\Data\Extraction\GroupCalibration;
use App\Models\ExtractionField;
use Illuminate\Support\Collection;

/**
 * Split-conformal risk control over extracted fields, calibrated per group.
 *
 * WHAT IS GUARANTEED. For a field drawn exchangeably from the same group as the
 * calibration set, this controls the *false acceptance rate*
 *
 *     P(field is auto-accepted AND wrong) <= alpha
 *
 * That is an unconditional joint probability. It is deliberately NOT the
 * conditional quantity P(wrong | accepted), which is a ratio of two random
 * quantities and is not what this construction bounds. The conditional rate is
 * computed and reported as an empirical diagnostic, never as a guarantee.
 *
 * WHY A FIELD-LEVEL LOSS. Sequence metrics such as character error rate are
 * non-decomposable and cannot carry a distribution-free bound. A per-field 0/1
 * loss is bounded and decomposable, so the standard conformal risk control
 * argument applies — and field correctness is also what governance depends on.
 *
 * WHY PER GROUP. A threshold fitted across all publishers and scripts at once
 * can report a healthy pooled rate while a low-resource subset fails badly,
 * because the majority group dominates the average. Each publisher x script
 * group therefore gets its own threshold and its own honest verdict.
 */
class ConformalCalibrator
{
    /**
     * Smallest calibration set that can certify anything at this alpha.
     *
     * The finite-sample correction contributes 1/(n+1) to the risk bound, so a
     * group with fewer than 1/alpha - 1 examples cannot satisfy the bound at any
     * threshold. This is the Mondrian small-group problem stated exactly, and it
     * bites low-resource groups first — which is precisely why it is reported
     * rather than smoothed over.
     */
    public function minimumCalibrationSize(float $alpha): int
    {
        if ($alpha <= 0.0 || $alpha >= 1.0) {
            return PHP_INT_MAX;
        }

        return max(1, (int) ceil(1.0 / $alpha) - 1);
    }

    /**
     * @return Collection<string, GroupCalibration>
     */
    public function calibrate(float $alpha, string $split = 'calibration'): Collection
    {
        return ExtractionField::query()
            ->calibratable()
            ->where('calibration_split', $split)
            ->get(['publisher_group', 'script_class', 'nonconformity_score', 'is_correct'])
            ->groupBy(fn (ExtractionField $field): string => $field->publisher_group.'|'.$field->script_class)
            ->map(fn (Collection $rows): GroupCalibration => $this->calibrateGroup($rows, $alpha))
            ->keyBy(fn (GroupCalibration $calibration): string => $calibration->key());
    }

    /**
     * @param  Collection<int, ExtractionField>  $fields
     */
    private function calibrateGroup(Collection $fields, float $alpha): GroupCalibration
    {
        $first = $fields->firstOrFail();
        $group = (string) $first->publisher_group;
        $script = (string) $first->script_class;

        $rows = [];

        foreach ($fields as $field) {
            $rows[] = [
                'score' => (float) $field->nonconformity_score,
                'wrong' => $field->is_correct === false,
            ];
        }

        $n = count($rows);
        $minimum = $this->minimumCalibrationSize($alpha);

        usort($rows, fn (array $a, array $b): int => $a['score'] <=> $b['score']);

        $threshold = null;
        $errorsAtThreshold = 0;
        $acceptedAtThreshold = 0;

        // Accept a field when its nonconformity score is at or below the
        // threshold, so risk is non-decreasing in the threshold. Scan upward and
        // keep the largest threshold whose finite-sample corrected risk still
        // satisfies alpha; that maximizes coverage subject to the bound.
        $errors = 0;

        foreach ($rows as $index => $row) {
            if ($row['wrong']) {
                $errors++;
            }

            // The next candidate score is a distinct value, so only evaluate at
            // the end of a run of ties: accepting part of a tie is not realizable.
            if ($index + 1 < $n && $rows[$index + 1]['score'] === $row['score']) {
                continue;
            }

            if (($errors + 1) / ($n + 1) <= $alpha) {
                $threshold = $row['score'];
                $errorsAtThreshold = $errors;
                $acceptedAtThreshold = $index + 1;
            }
        }

        return new GroupCalibration(
            $group,
            $script,
            $alpha,
            $n,
            $minimum,
            $threshold,
            $n > 0 ? round($errorsAtThreshold / $n, 6) : 0.0,
            $n > 0 ? round($acceptedAtThreshold / $n, 6) : 0.0,
            $acceptedAtThreshold > 0 ? round($errorsAtThreshold / $acceptedAtThreshold, 6) : null,
        );
    }
}
