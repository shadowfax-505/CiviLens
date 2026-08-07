<?php

namespace App\Services\Extraction;

use App\Data\Extraction\GroupCalibration;
use App\Models\ExtractionField;
use Illuminate\Support\Collection;

class CalibrationReport
{
    public function __construct(private readonly ConformalCalibrator $calibrator) {}

    /**
     * @return array<string, mixed>
     */
    public function build(float $alpha, string $split = 'test'): array
    {
        $groupwise = $this->calibrator->calibrate($alpha);
        $pooled = $this->pooledThreshold($alpha);

        return [
            'alpha' => $alpha,
            'minimum_calibration_size' => $this->calibrator->minimumCalibrationSize($alpha),
            'guaranteed_quantity' => 'P(field auto-accepted AND wrong) <= alpha',
            'not_guaranteed' => 'P(wrong | accepted) is reported empirically only',
            'groups' => $groupwise->map(fn (GroupCalibration $c): array => $c->toArray())->values()->all(),
            'uncertifiable_groups' => $groupwise->reject(fn (GroupCalibration $c): bool => $c->certifiable())
                ->map(fn (GroupCalibration $c): string => $c->key())->values()->all(),
            'label_leakage' => $this->labelLeakage(),
            'realized' => $this->realized($groupwise, $pooled, $alpha, $split),
        ];
    }

    /**
     * Detect a nonconformity score that already knows the answer.
     *
     * If no incorrect field scores below the worst correct field, the score
     * separates outcomes perfectly. In practice that means the score was derived
     * from the gold value rather than predicted independently of it, and any
     * guarantee computed on top of it is vacuous. This is cheap to check and
     * catastrophic to miss, so it is reported beside every calibration.
     *
     * @return array<string, mixed>
     */
    private function labelLeakage(): array
    {
        $scored = ExtractionField::query()->calibratable();
        $total = (clone $scored)->count();

        if ($total === 0) {
            return ['checked' => 0, 'suspected' => false, 'reason' => null];
        }

        $worstCorrect = (clone $scored)->where('is_correct', true)->max('nonconformity_score');
        $incorrectBelow = $worstCorrect === null
            ? 0
            : (clone $scored)->where('is_correct', false)->where('nonconformity_score', '<', (float) $worstCorrect)->count();
        $distinct = (clone $scored)->distinct()->count('nonconformity_score');

        $suspected = $worstCorrect !== null && $incorrectBelow === 0;

        return [
            'checked' => $total,
            'distinct_scores' => $distinct,
            'incorrect_scoring_below_worst_correct' => $incorrectBelow,
            'suspected' => $suspected,
            'reason' => $suspected
                ? 'No incorrect field scores below the worst correct field. The nonconformity score likely derives from the gold value rather than an independent prediction, which makes any guarantee computed from it vacuous.'
                : null,
        ];
    }

    /**
     * A single threshold fitted across every group at once, the way a marginal
     * guarantee would be built. Kept only for comparison: its realized error on a
     * low-resource subgroup is the argument for group-conditional calibration.
     */
    private function pooledThreshold(float $alpha): ?float
    {
        $rows = ExtractionField::query()
            ->calibratable()
            ->where('calibration_split', 'calibration')
            ->orderBy('nonconformity_score')
            ->get(['nonconformity_score', 'is_correct']);

        $n = $rows->count();
        $errors = 0;
        $threshold = null;

        foreach ($rows as $index => $row) {
            if ($row->is_correct === false) {
                $errors++;
            }

            $next = $rows->get($index + 1);

            if ($next !== null && (float) $next->nonconformity_score === (float) $row->nonconformity_score) {
                continue;
            }

            if (($errors + 1) / ($n + 1) <= $alpha) {
                $threshold = (float) $row->nonconformity_score;
            }
        }

        return $threshold;
    }

    /**
     * Measure both strategies on held-out data, per group.
     *
     * The expected shape of this table is that the pooled threshold satisfies
     * alpha overall while exceeding it on the low-resource subgroup — a guarantee
     * that hides the failure it was supposed to catch.
     *
     * @param  Collection<string, GroupCalibration>  $groupwise
     * @return array<string, mixed>
     */
    private function realized(Collection $groupwise, ?float $pooled, float $alpha, string $split): array
    {
        $rows = ExtractionField::query()
            ->calibratable()
            ->where('calibration_split', $split)
            ->get(['publisher_group', 'script_class', 'nonconformity_score', 'is_correct']);

        if ($rows->isEmpty()) {
            return ['split' => $split, 'fields' => 0, 'groups' => [], 'overall' => null];
        }

        $perGroup = $rows
            ->groupBy(fn (ExtractionField $f): string => $f->publisher_group.'|'.$f->script_class)
            ->map(function (Collection $groupRows, string $key) use ($groupwise, $pooled, $alpha): array {
                $calibration = $groupwise->get($key);
                $conditional = $this->rate($groupRows, $calibration?->certifiable() === true ? $calibration->threshold : null);
                $marginal = $this->rate($groupRows, $pooled);

                return [
                    'group' => $key,
                    'fields' => $groupRows->count(),
                    'group_conditional' => $conditional,
                    'pooled' => $marginal,
                    'pooled_breaches_alpha' => $marginal['false_acceptance_rate'] > $alpha,
                ];
            });

        return [
            'split' => $split,
            'fields' => $rows->count(),
            'pooled_threshold' => $pooled,
            'overall' => [
                'group_conditional' => null,
                'pooled' => $this->rate($rows, $pooled),
            ],
            'groups' => $perGroup->values()->all(),
            'groups_where_pooled_breaches_alpha' => $perGroup->where('pooled_breaches_alpha', true)
                ->map(fn (array $row): string => $row['group'])->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, ExtractionField>  $rows
     * @return array<string, float|int|null>
     */
    private function rate(Collection $rows, ?float $threshold): array
    {
        $total = $rows->count();

        if ($threshold === null) {
            return ['accepted' => 0, 'acceptance_rate' => 0.0, 'false_acceptance_rate' => 0.0, 'error_among_accepted' => null];
        }

        $accepted = $rows->filter(fn (ExtractionField $f): bool => (float) $f->nonconformity_score <= $threshold);
        $wrong = $accepted->filter(fn (ExtractionField $f): bool => $f->is_correct === false)->count();

        return [
            'accepted' => $accepted->count(),
            'acceptance_rate' => $total > 0 ? round($accepted->count() / $total, 6) : 0.0,
            'false_acceptance_rate' => $total > 0 ? round($wrong / $total, 6) : 0.0,
            'error_among_accepted' => $accepted->count() > 0 ? round($wrong / $accepted->count(), 6) : null,
        ];
    }
}
