<?php

namespace App\Data\Extraction;

final readonly class GroupCalibration
{
    public function __construct(
        public string $publisherGroup,
        public string $scriptClass,
        /**
         * Which kind of value this threshold was fitted on.
         *
         * The stratum is publisher x script x kind. Publisher and script alone
         * was enough while every value was a figure — reading a Bengali amount
         * and a Bengali date are close to the same task — and court records
         * broke it: `supremecourt|en` was labelled 105 dates and 15 amounts, and
         * a threshold from those was about to certify 2,308 case numbers and 972
         * statutory references, which are multi-word values with their own
         * punctuation and their own ways of going wrong.
         *
         * Refusing an unlabelled kind closed the overclaim but could not open
         * the other side: a kind becomes certifiable only by being calibrated,
         * and calibrating it means making it the stratum. That also makes a
         * kind-filtered review draw uniform inside its own stratum, which is
         * what lets nineteen judgements buy a kind directly instead of the
         * ~340 a uniform draw over the group would cost.
         */
        public string $fieldKey,
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
         * 'stratum' this publisher, script and kind — the only conditional
         * case; 'group' every kind this publisher writes in this script;
         * 'script' every publisher writing this script; 'marginal' everything;
         * 'none' nothing was certified.
         */
        public string $basis = 'stratum',
    ) {}

    public function key(): string
    {
        return $this->publisherGroup.'|'.$this->scriptClass.'|'.$this->fieldKey;
    }

    /** The publisher and script alone, which is the rung above this stratum. */
    public function groupKey(): string
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
        return $this->basis === 'stratum';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'publisher_group' => $this->publisherGroup,
            'script_class' => $this->scriptClass,
            'field_key' => $this->fieldKey,
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
        ];
    }
}
