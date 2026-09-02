<?php

namespace App\Data\Extraction;

final readonly class GroupCalibration
{
    public function __construct(
        public string $publisherGroup,
        public string $scriptClass,
        public float $alpha,
        public int $calibrationSize,
        public int $minimumCalibrationSize,
        public ?float $threshold,
        public float $empiricalFalseAcceptanceRate,
        public float $acceptanceRate,
        public ?float $empiricalErrorAmongAccepted,
        /** Smallest alpha this group's own count can satisfy: 1/(n+1). */
        public float $attainableAlpha = 1.0,
        /** The alpha actually certified at, which is never tighter than that. */
        public float $certifiedAlpha = 1.0,
        /**
         * Which population the threshold was calibrated on:
         * 'group' this publisher and script, 'script' every publisher writing
         * this script, 'marginal' everything, 'none' nothing was certified.
         */
        public string $basis = 'group',
        /**
         * Which kinds of value the labels behind this threshold were.
         *
         * A threshold is fitted on labelled values and applied to unlabelled
         * ones, and that is only sound while the two are alike. The group key
         * is publisher and script, which was enough while every value was a
         * figure: reading a Bengali amount and a Bengali date are close to the
         * same task. Court records broke it. `supremecourt|en` was labelled
         * 105 dates, 15 amounts and 3 references, and would have certified
         * 2,308 case numbers, 972 statutory references and 855 law report
         * citations against it — multi-word values with their own punctuation
         * and their own way of going wrong, of which nobody has judged one.
         *
         * @var list<string>
         */
        public array $calibratedKinds = [],
    ) {}

    /**
     * Does this threshold rest on any label of this kind of value?
     *
     * Absence is the case worth refusing. A kind nobody has judged is not a
     * harder case of a kind they have — it is a population the calibration set
     * says nothing about, exactly as a group with no labels is, and this class
     * already defers that one.
     *
     * An empty list means nothing was recorded rather than nothing was
     * labelled — a calibration fitted on real fields always has at least one
     * kind — so it covers everything and leaves older behaviour alone.
     */
    public function covers(string $fieldKey): bool
    {
        return $this->calibratedKinds === [] || in_array($fieldKey, $this->calibratedKinds, true);
    }

    public function key(): string
    {
        return $this->publisherGroup.'|'.$this->scriptClass;
    }

    /**
     * A group with too few calibration examples cannot certify anything at this
     * alpha. Deferring every field in it is the correct behaviour, not a bug.
     */
    public function certifiable(): bool
    {
        return $this->threshold !== null;
    }

    /**
     * Is the guarantee about this group, or about a wider population it sits in?
     *
     * A borrowed threshold is valid for the pool it was calibrated on and says
     * nothing conditional about a rare group inside it. Reporting the two as one
     * would be the exact overclaim per-group calibration exists to prevent.
     */
    public function conditional(): bool
    {
        return $this->basis === 'group';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'publisher_group' => $this->publisherGroup,
            'script_class' => $this->scriptClass,
            'alpha' => $this->alpha,
            'calibration_size' => $this->calibrationSize,
            'minimum_calibration_size' => $this->minimumCalibrationSize,
            'certifiable' => $this->certifiable(),
            'threshold' => $this->threshold,
            'empirical_false_acceptance_rate' => $this->empiricalFalseAcceptanceRate,
            'acceptance_rate' => $this->acceptanceRate,
            'empirical_error_among_accepted' => $this->empiricalErrorAmongAccepted,
            'attainable_alpha' => round($this->attainableAlpha, 6),
            'certified_alpha' => round($this->certifiedAlpha, 6),
            'basis' => $this->basis,
            'conditional' => $this->conditional(),
            'calibrated_kinds' => $this->calibratedKinds,
        ];
    }
}
