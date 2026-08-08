<?php

namespace App\Data\Intelligence;

final readonly class PeerComparison
{
    /** @param list<string> $cohortReferences */
    public function __construct(
        public string $subjectReference,
        public string $cohortKey,
        public int $cohortSize,
        public int $minimumCohortSize,
        public ?float $subjectValue,
        public ?float $cohortMedian,
        public ?float $ratioToMedian,
        public ?float $robustDeviation,
        public array $cohortReferences,
        public string $statement,
    ) {}

    public function comparable(): bool
    {
        return $this->cohortMedian !== null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'subject' => $this->subjectReference,
            'cohort_key' => $this->cohortKey,
            'cohort_size' => $this->cohortSize,
            'minimum_cohort_size' => $this->minimumCohortSize,
            'comparable' => $this->comparable(),
            'subject_value' => $this->subjectValue,
            'cohort_median' => $this->cohortMedian,
            'ratio_to_median' => $this->ratioToMedian,
            'robust_deviation' => $this->robustDeviation,
            'cohort_references' => $this->cohortReferences,
            'statement' => $this->statement,
        ];
    }
}
