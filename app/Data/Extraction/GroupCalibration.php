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
        ];
    }
}
