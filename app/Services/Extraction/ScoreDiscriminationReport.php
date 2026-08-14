<?php

namespace App\Services\Extraction;

use App\Models\ExtractionField;
use Illuminate\Support\Collection;

/**
 * Does the score tell wrong readings from right ones at all?
 *
 * A conformal bound holds for any score, so validity says nothing about whether
 * the score is worth thresholding. This reports the one number that does: the
 * probability that a randomly chosen wrong field scores above a randomly chosen
 * correct one. Half is a coin flip, which is what the page-confidence score
 * measured at.
 */
class ScoreDiscriminationReport
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $labelled = ExtractionField::query()
            ->whereNotNull('is_correct')
            ->whereNotNull('nonconformity_score')
            ->get(['is_correct', 'nonconformity_score', 'publisher_group', 'script_class', 'score_basis']);

        $groups = [];

        foreach ($labelled->groupBy(fn (ExtractionField $f): string => $f->publisher_group.'|'.$f->script_class) as $key => $rows) {
            $groups[] = ['group' => (string) $key] + $this->auc($rows);
        }

        return [
            // How much of the labelled set the score even applies to. A score
            // that discriminates well over three quarters of the corpus and is
            // silent on the rest is two facts, and reporting only the first
            // would overstate it.
            'coverage' => [
                'scored' => $labelled->count(),
                'unscored' => ExtractionField::query()->whereNotNull('is_correct')->whereNull('nonconformity_score')->count(),
            ],
            'overall' => $this->auc($labelled),
            'groups' => $groups,
            'by_basis' => $labelled->countBy(fn (ExtractionField $f): string => (string) ($f->score_basis ?? 'none'))->all(),
        ];
    }

    /**
     * A collection rather than a list: grouping preserves keys, and the two
     * callers would otherwise both have to launder them.
     *
     * @param  Collection<int, ExtractionField>  $fields
     * @return array{auc: float|null, wrong: int, correct: int, reason: string|null}
     */
    private function auc(Collection $fields): array
    {
        $wrong = [];
        $right = [];

        foreach ($fields as $field) {
            $field->is_correct === false
                ? $wrong[] = (float) $field->nonconformity_score
                : $right[] = (float) $field->nonconformity_score;
        }

        // Undecidable rather than zero: a set with one outcome cannot show a
        // score separating outcomes or failing to, and reporting a number there
        // would be inventing one.
        if ($wrong === [] || $right === []) {
            return [
                'auc' => null,
                'wrong' => count($wrong),
                'correct' => count($right),
                'reason' => 'Undecidable: only one outcome is present.',
            ];
        }

        $wins = 0.0;

        foreach ($wrong as $bad) {
            foreach ($right as $good) {
                $wins += $bad > $good ? 1.0 : ($bad === $good ? 0.5 : 0.0);
            }
        }

        return [
            'auc' => round($wins / (count($wrong) * count($right)), 4),
            'wrong' => count($wrong),
            'correct' => count($right),
            'reason' => null,
        ];
    }
}
