<?php

namespace App\Services\Extraction;

use App\Models\ExtractionField;
use App\Services\Statistics\ClopperPearson;

/**
 * What this method cannot do, with the numbers that say so.
 *
 * Written as a report rather than as prose because every limitation here was
 * found by measuring, and several claims made about this project were true when
 * written and false a day later. A limitations section transcribed by hand goes
 * stale in exactly the direction that flatters the work.
 */
class LimitationsReport
{
    public function __construct(
        private readonly CalibrationReport $calibration,
        private readonly ScoreDiscriminationReport $discrimination,
        private readonly ReviewerAgreementReport $agreement,
        private readonly ClopperPearson $clopperPearson,
    ) {}

    /**
     * @return list<array{limitation: string, evidence: array<string, mixed>, why_it_matters: string}>
     */
    public function build(float $alpha = 0.05): array
    {
        return array_values(array_filter([
            $this->singlePublisher(),
            $this->sampleCannotConfirm($alpha),
            $this->thresholdExcludesNothing($alpha),
            $this->correlatedSecondRead(),
            $this->unlocatable(),
            $this->agreementNeverMeasured(),
        ]));
    }

    /**
     * @return array{limitation: string, evidence: array<string, mixed>, why_it_matters: string}
     */
    private function singlePublisher(): array
    {
        $byGroup = ExtractionField::query()
            ->where('decision', 'accepted')
            ->selectRaw('publisher_group, count(*) as aggregate')
            ->groupBy('publisher_group')
            ->pluck('aggregate', 'publisher_group')
            ->map(fn (int|string $value): int => (int) $value)
            ->all();

        return [
            'limitation' => 'Every certified value comes from one publisher.',
            'evidence' => ['certified_by_publisher' => $byGroup],
            'why_it_matters' => 'Group-conditional calibration exists so a low-resource subset cannot '
                .'hide behind a healthy average, and that argument is worth little while every group '
                .'belongs to the same publisher. The three groups are one publisher\'s Bengali, '
                .'English and mixed pages, which is a much narrower claim than "Bangladesh public '
                .'sources".',
        ];
    }

    /**
     * @return array{limitation: string, evidence: array<string, mixed>, why_it_matters: string}
     */
    private function sampleCannotConfirm(float $alpha): array
    {
        $realized = $this->calibration->build($alpha)['realized'];
        $cannotClear = $realized['groups_the_sample_cannot_clear'] ?? [];

        return [
            'limitation' => 'The held-out sample cannot confirm the guarantee it reports.',
            'evidence' => [
                'alpha' => $alpha,
                'groups_whose_interval_reaches_past_alpha' => $cannotClear,
                'example_at_a_3_percent_rate' => [
                    'n_400' => $this->clopperPearson->interval(12, 400),
                    'n_500' => $this->clopperPearson->interval(15, 500),
                ],
            ],
            'why_it_matters' => 'The conformal bound is a statement about the draw, not about one '
                .'test set, and it is unaffected. But a realized rate inside alpha is weak evidence '
                .'when the exact interval around it reaches well past alpha, and on this corpus that '
                .'is every group. At the error rate observed here the interval first clears alpha at '
                .'about 500 held-out labels, roughly eight times the adjudication done so far.',
        ];
    }

    /**
     * Groups whose threshold turns almost nothing away.
     *
     * Asked as a share rather than as a literal ceiling. A threshold of 1.0
     * excludes nothing by definition, but one of 0.9 on a score that rarely
     * exceeds it excludes nearly nothing in practice, and the second is the
     * state this corpus is actually in.
     *
     * @return array{limitation: string, evidence: array<string, mixed>, why_it_matters: string}|null
     */
    private function thresholdExcludesNothing(float $alpha): ?array
    {
        $barelyExcluding = [];

        foreach ($this->calibration->build($alpha)['groups'] as $group) {
            if (($group['certifiable'] ?? false) !== true) {
                continue;
            }

            $key = $group['publisher_group'].'|'.$group['script_class'];
            $threshold = (float) ($group['threshold'] ?? 0);

            $scored = ExtractionField::query()
                ->where('publisher_group', $group['publisher_group'])
                ->where('script_class', $group['script_class'])
                ->whereNotNull('nonconformity_score');

            $total = (clone $scored)->count();

            if ($total === 0) {
                continue;
            }

            $excluded = (clone $scored)->where('nonconformity_score', '>', $threshold)->count();
            $share = $excluded / $total;

            if ($share <= 0.01) {
                $barelyExcluding[$key] = [
                    'threshold' => round($threshold, 6),
                    'scored' => $total,
                    'excluded_by_the_threshold' => $excluded,
                    'share_excluded' => round($share, 6),
                ];
            }
        }

        if ($barelyExcluding === []) {
            return null;
        }

        return [
            'limitation' => 'For some groups the threshold turns away almost nothing.',
            'evidence' => ['groups' => $barelyExcluding],
            'why_it_matters' => 'Where the measured error rate already sits inside alpha, conformal '
                .'correctly asks for little or no threshold: the quantile lands near the top of the '
                .'score range and nearly every scored value is accepted. The score\'s discrimination '
                .'buys almost nothing there and would only begin to bite at a tighter alpha, so '
                .'acceptance should not be read as evidence that a value scored well.',
        ];
    }

    /**
     * @return array{limitation: string, evidence: array<string, mixed>, why_it_matters: string}
     */
    private function correlatedSecondRead(): array
    {
        return [
            'limitation' => 'The second read can agree with the first about something they are both wrong about.',
            'evidence' => [
                'values_deferred_for_a_disputed_sign' => ExtractionField::query()->where('score_basis', 'sign-disagreement')->count(),
                'observed_case' => 'Pages write a difference as (A-B) -C, where the last mark is an '
                    .'equals sign. Both readings return a hyphen, agree, and the figure certifies as '
                    .'negative when the arithmetic shows it positive.',
            ],
            'why_it_matters' => 'It is the same engine family reading the same glyph, so a '
                .'systematic misreading is reproduced rather than caught. Where the two readings do '
                .'disagree about a sign the value is now deferred by rule; where they agree wrongly, '
                .'only a person looking at the page finds it, and one did.',
        ];
    }

    /**
     * @return array{limitation: string, evidence: array<string, mixed>, why_it_matters: string}
     */
    private function unlocatable(): array
    {
        $unlocatable = ExtractionField::query()->where('score_basis', 'unlocatable')->count();
        $scored = ExtractionField::query()->whereNotNull('score_basis')->count();

        return [
            'limitation' => 'A share of values cannot be found again on their own page, and defer by rule.',
            'evidence' => [
                'unlocatable' => $unlocatable,
                'scored' => $scored,
                'share' => $scored > 0 ? round($unlocatable / $scored, 4) : null,
            ],
            'why_it_matters' => 'Such a value is never accepted, so it cannot breach the bound — and '
                .'that is exactly why this is easy to miss. Twice this week a mis-cropped population '
                .'simply abstained, and the guarantee stayed true over a quietly narrower set. '
                .'Coverage is where this method goes wrong without saying so.',
        ];
    }

    /**
     * @return array{limitation: string, evidence: array<string, mixed>, why_it_matters: string}|null
     */
    private function agreementNeverMeasured(): ?array
    {
        $agreement = $this->agreement->build();

        if (($agreement['double_judged'] ?? 0) > 0) {
            return null;
        }

        return [
            'limitation' => 'Nobody has checked whether two people would judge these values the same way.',
            'evidence' => [
                'double_judged' => 0,
                'labels_per_hour' => $agreement['labels_per_hour'] ?? null,
                'discrimination' => $this->discrimination->build()['overall'] ?? null,
            ],
            'why_it_matters' => 'The bound is computed from human labels and is only as trustworthy '
                .'as those labels are reproducible. The machinery to measure it exists; the '
                .'measurement needs a second reviewer, and until it is taken this is the largest '
                .'unexamined assumption in the method.',
        ];
    }
}
