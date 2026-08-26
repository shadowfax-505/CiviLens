<?php

namespace App\Services\Extraction;

use App\Models\ExtractionField;
use App\Models\ExtractionFieldJudgement;
use Illuminate\Support\Facades\DB;

/**
 * How often two people judging the same scanned figure agree.
 *
 * The bound is computed from human labels, so it is only as trustworthy as
 * those labels are reproducible. Reporting a risk level of 0.05 while never
 * having measured whether two readers would even give the same answer is the
 * largest unexamined assumption in the method, and it is the one a reviewer of
 * the paper would reach for first.
 *
 * Cohen's kappa rather than raw agreement, because these judgements are heavily
 * skewed — most values are read correctly — and two people agreeing 95% of the
 * time on a task where "correct" is the answer 95% of the time have shown
 * nothing. Kappa asks how much better than that they did.
 */
class ReviewerAgreementReport
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $doubled = DB::table('extraction_field_judgements')
            ->select('extraction_field_id')
            ->groupBy('extraction_field_id')
            ->havingRaw('count(distinct user_id) >= 2')
            ->pluck('extraction_field_id');

        if ($doubled->isEmpty()) {
            return [
                'double_judged' => 0,
                'agreement' => null,
                'kappa' => null,
                // Said plainly rather than reported as a zero, which reads like
                // a measurement that came out badly.
                'reason' => 'No value has been judged by two people yet, so agreement cannot be measured.',
                'labels_per_hour' => $this->labelsPerHour(),
            ];
        }

        $pairs = [];

        foreach ($doubled as $fieldId) {
            $verdicts = ExtractionFieldJudgement::query()
                ->where('extraction_field_id', $fieldId)
                ->orderBy('id')
                ->pluck('verdict')
                ->all();

            // The first two, so a third reviewer does not silently reweight a
            // pair that has already been counted.
            $pairs[] = [$verdicts[0], $verdicts[1]];
        }

        return [
            'double_judged' => count($pairs),
            'agreement' => $this->rawAgreement($pairs),
            'kappa' => $this->kappa($pairs),
            'disagreements' => $this->disagreements($pairs),
            'reason' => null,
            'labels_per_hour' => $this->labelsPerHour(),
        ];
    }

    /**
     * @param  list<array{0: string, 1: string}>  $pairs
     */
    private function rawAgreement(array $pairs): float
    {
        $same = count(array_filter($pairs, fn (array $pair): bool => $pair[0] === $pair[1]));

        return round($same / count($pairs), 4);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $pairs
     */
    private function kappa(array $pairs): ?float
    {
        $n = count($pairs);
        $observed = $this->rawAgreement($pairs);

        $first = [];
        $second = [];

        foreach ($pairs as [$a, $b]) {
            $first[$a] = ($first[$a] ?? 0) + 1;
            $second[$b] = ($second[$b] ?? 0) + 1;
        }

        $expected = 0.0;

        foreach ($first as $verdict => $count) {
            $expected += ($count / $n) * (($second[$verdict] ?? 0) / $n);
        }

        // Both reviewers gave one answer to everything. Kappa is undefined
        // there, and returning 0 would report perfect agreement as no better
        // than chance.
        if (abs(1.0 - $expected) < 1e-9) {
            return null;
        }

        return round(($observed - $expected) / (1.0 - $expected), 4);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $pairs
     * @return array<string, int>
     */
    private function disagreements(array $pairs): array
    {
        $kinds = [];

        foreach ($pairs as [$a, $b]) {
            if ($a === $b) {
                continue;
            }

            $pair = [$a, $b];
            sort($pair);
            $kinds[implode(' vs ', $pair)] = ($kinds[implode(' vs ', $pair)] ?? 0) + 1;
        }

        arsort($kinds);

        return $kinds;
    }

    /**
     * Judgements per hour of actual reviewing.
     *
     * Counted within sessions rather than across the calendar: labels made on
     * three days spread over a fortnight would otherwise read as one an hour.
     * A gap longer than the session break ends a session.
     */
    private function labelsPerHour(): ?float
    {
        $times = ExtractionField::query()
            ->whereNotNull('reviewed_at')
            ->orderBy('reviewed_at')
            ->pluck('reviewed_at');

        if ($times->count() < 2) {
            return null;
        }

        $seconds = 0.0;
        $breakSeconds = 600;

        foreach ($times as $index => $time) {
            if ($index === 0) {
                continue;
            }

            $gap = $time->diffInSeconds($times[$index - 1], absolute: true);
            $seconds += $gap <= $breakSeconds ? $gap : 0;
        }

        return $seconds <= 0.0 ? null : round($times->count() / ($seconds / 3600), 1);
    }
}
