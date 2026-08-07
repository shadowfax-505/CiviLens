<?php

namespace App\Services\Extraction;

use App\Data\Extraction\GroupCalibration;
use App\Models\ExtractionField;
use Illuminate\Support\Collection;

class FieldDecisionService
{
    public const ACCEPTED = 'accepted';

    public const DEFERRED = 'deferred';

    public function __construct(private readonly ConformalCalibrator $calibrator) {}

    /**
     * Apply group thresholds to fields awaiting a decision.
     *
     * Deny by default: a field whose group has no calibration, too little
     * calibration to certify at this alpha, or a missing score is deferred to a
     * human. Nothing is auto-accepted on the basis of an absent guarantee.
     *
     * @return array<string, int>
     */
    public function decide(float $alpha, string $targetSplit = 'test'): array
    {
        $calibrations = $this->calibrator->calibrate($alpha);
        $counts = [self::ACCEPTED => 0, self::DEFERRED => 0];

        ExtractionField::query()
            ->where('calibration_split', $targetSplit)
            ->orderBy('id')
            ->chunkById(500, function (Collection $fields) use ($calibrations, $alpha, &$counts): void {
                foreach ($fields as $field) {
                    $calibration = $calibrations->get($field->publisher_group.'|'.$field->script_class);
                    $decision = $this->decisionFor($field, $calibration);

                    $field->forceFill([
                        'decision' => $decision,
                        'decision_alpha' => $alpha,
                    ])->save();

                    $counts[$decision]++;
                }
            });

        return $counts;
    }

    private function decisionFor(ExtractionField $field, ?GroupCalibration $calibration): string
    {
        if (! $calibration instanceof GroupCalibration || ! $calibration->certifiable()) {
            return self::DEFERRED;
        }

        if ($field->nonconformity_score === null) {
            return self::DEFERRED;
        }

        return (float) $field->nonconformity_score <= $calibration->threshold
            ? self::ACCEPTED
            : self::DEFERRED;
    }
}
