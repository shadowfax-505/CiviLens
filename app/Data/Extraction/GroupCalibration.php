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
    ) {}

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
        ];
    }
}
