<?php

namespace App\Services\Extraction;

use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Models\ExtractionRun;
use App\Models\SourceArtifactVersion;
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
    /**
     * Characters a Latin codepage produces when it is handed Bengali glyphs.
     * None of them is common in genuine English or in Unicode Bengali.
     */
    private const LEGACY_FONT_RESIDUE = '/[\x{00AA}\x{00BA}\x{0161}\x{00BF}\x{2021}\x{00D6}\x{201D}\x{00A8}\x{00AE}\x{00F5}\x{00B3}]/u';

    /** Below this, a stray symbol or two says nothing about the page. */
    private const MINIMUM_PAGE_LENGTH = 200;

    /** One in sixty characters. A page of English does not reach it. */
    private const RESIDUE_DENSITY = 60;

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
            $this->scriptClassIsNotLanguage(),
            $this->sameFileHeldTwice(),
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
                .'belongs to the same publisher. The three groups are one publisher\'s pages divided '
                .'by script, which is a much narrower claim than "Bangladesh public sources" — and '
                .'narrower still than the group names suggest; see the limitation on what "en" '
                .'actually contains.',
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
                'values_deferred_for_mixing_two_alphabets' => ExtractionField::query()->where('score_basis', 'mixed-script-numeral')->count(),
                'values_deferred_for_a_disputed_leading_digit' => ExtractionField::query()->where('score_basis', 'leading-digit-disputed')->count(),
                'observed_case' => 'Pages write a difference as (A-B) -C, where the last mark is an '
                    .'equals sign. Both readings return a hyphen, agree, and the figure certifies as '
                    .'negative when the arithmetic shows it positive.',
                'third_observed_case' => 'Readings of ১০০০০ and ৯০০০০ for the same crop — ten '
                    .'thousand against ninety thousand — differ in one character of five and '
                    .'certified. So did 39656.49 against 639656.49, a sixteenfold difference. The '
                    .'disagreement that matters most is the one a metric over characters weighs '
                    .'least.',
                'second_observed_case' => 'A numeral read as 1১৪০৭৭.৩৩ was read [১৪০৭৭.৩৩ on the '
                    .'second pass: a table rule line taken for a leading digit by one pass and a '
                    .'bracket by the other. Edit distance called that a one-character difference and '
                    .'the figure certified at ten times the value on the page. 58 such values were '
                    .'published before a numeral mixing two alphabets began deferring by rule.',
            ],
            'why_it_matters' => 'It is the same engine family reading the same glyph, so a '
                .'systematic misreading is reproduced rather than caught. Where the two readings do '
                .'disagree about a sign the value is now deferred by rule; where they agree wrongly, '
                .'only a person looking at the page finds it, and one did. The mixed-alphabet rule '
                .'catches a related case cheaply, because a document writes a number in one script '
                .'and two alphabets in one numeral can only be a reading artefact — but it catches '
                .'it by its shape, not by understanding it, and a rule line misread as a digit in a '
                .'page that is wholly Bengali or wholly Latin still certifies unseen.',
        ];
    }

    /**
     * @return array{limitation: string, evidence: array<string, mixed>, why_it_matters: string}|null
     */
    private function scriptClassIsNotLanguage(): ?array
    {
        $residue = $this->legacyFontPageIds();

        if ($residue === []) {
            return null;
        }

        $byScript = [];

        foreach (['bn', 'en', 'mixed'] as $script) {
            $accepted = ExtractionField::query()->where('decision', 'accepted')->where('script_class', $script);
            $labelled = ExtractionField::query()->whereNotNull('is_correct')->where('script_class', $script);

            $byScript[$script] = [
                'accepted' => (clone $accepted)->count(),
                'accepted_on_legacy_font_pages' => (clone $accepted)->whereIn('extraction_page_id', $residue)->count(),
                'labels' => (clone $labelled)->count(),
                'labels_on_legacy_font_pages' => (clone $labelled)->whereIn('extraction_page_id', $residue)->count(),
            ];
        }

        return [
            'limitation' => 'The script classes are named after languages and one of them is not a language.',
            'evidence' => [
                'pages_with_heavy_legacy_font_residue' => count($residue),
                'by_script_class' => $byScript,
            ],
            'why_it_matters' => 'Bengali set in a pre-Unicode font stores its characters as Latin '
                .'bytes, so the classifier reads such a page as English. Where every accepted value '
                .'and every label in a group sits on those pages, the group is homogeneous and its '
                .'guarantee is sound — the name is simply wrong, and a reader who sees a certified '
                .'rate for "en" will believe this corpus contains certified English documents when '
                .'it may contain none. Where the shares differ between the labels and the values '
                .'they calibrate, the group mixes two populations that fail differently, and '
                .'exchangeability — the assumption the whole bound rests on — is the thing at risk '
                .'rather than the arithmetic.',
        ];
    }

    /**
     * @return array{limitation: string, evidence: array<string, mixed>, why_it_matters: string}|null
     */
    private function sameFileHeldTwice(): ?array
    {
        $duplicates = SourceArtifactVersion::query()->duplicate()->count();

        if ($duplicates === 0) {
            return null;
        }

        $runs = ExtractionRun::query()
            ->whereIn('source_artifact_version_id', SourceArtifactVersion::query()->duplicate()->select('id'))
            ->select('id');

        $onDuplicates = ExtractionField::query()->whereIn('extraction_run_id', $runs);

        return [
            'limitation' => 'The archive holds some files twice, and the corpus is smaller than the artifact count.',
            'evidence' => [
                'artifacts' => SourceArtifactVersion::query()->count(),
                'distinct_files' => SourceArtifactVersion::query()->canonical()->count(),
                'second_copies' => $duplicates,
                'candidates_on_second_copies' => (clone $onDuplicates)->count(),
                'labelled_on_second_copies' => (clone $onDuplicates)->whereNotNull('is_correct')->count(),
                'accepted_on_second_copies' => (clone $onDuplicates)->where('decision', 'accepted')->count(),
            ],
            'why_it_matters' => 'The same document is published at more than one address and discovery '
                .'follows both, so the checksums match and both URLs are real provenance. The risk is '
                .'not storage but counting: a duplicated file duplicates its candidates, and a '
                .'calibration set drawn uniformly from that pool is not a sample of distinct '
                .'documents, which is what the bound assumes. Duplicates are now kept out of the '
                .'review queue, out of candidate generation and out of the published document list; '
                .'what remains is that candidates generated before that still exist, and the count '
                .'above says how many.',
        ];
    }

    /**
     * Pages whose text carries the residue of a pre-Unicode Bengali font.
     *
     * Measured from the stored text rather than trusted from a flag, because
     * the router that was meant to catch these reports nothing to reroute:
     * their text layer is dense and clean-looking, and only its alphabet is
     * wrong.
     *
     * @return list<int>
     */
    private function legacyFontPageIds(): array
    {
        $ids = [];

        ExtractionPage::query()
            ->select(['id', 'extracted_text'])
            ->whereNotNull('extracted_text')
            ->chunkById(2000, function ($pages) use (&$ids): void {
                foreach ($pages as $page) {
                    $text = (string) $page->extracted_text;
                    $length = mb_strlen($text);

                    if ($length < self::MINIMUM_PAGE_LENGTH) {
                        continue;
                    }

                    if (preg_match_all(self::LEGACY_FONT_RESIDUE, $text) > $length / self::RESIDUE_DENSITY) {
                        $ids[] = (int) $page->id;
                    }
                }
            });

        return $ids;
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
