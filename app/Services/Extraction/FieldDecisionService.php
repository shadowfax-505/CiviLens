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
    public function decide(float $alpha, ?string $targetSplit = null): array
    {
        // Adaptive, so a rare group is judged against the level its own labels
        // support rather than deferred wholesale.
        $calibrations = $this->calibrator->calibrateAdaptive($alpha);
        $counts = [self::ACCEPTED => 0, self::DEFERRED => 0];

        ExtractionField::query()
            ->when(
                $targetSplit !== null,
                fn ($query) => $query->where('calibration_split', $targetSplit),
                // The corpus, not the labelled sample. Deciding only the held-out
                // split left 4,252 extracted values sitting pending forever,
                // which is the whole pipeline stopping one step before it does
                // anything.
                fn ($query) => $query->whereNull('gold_source')->whereNull('calibration_split'),
            )
            ->orderBy('id')
            ->chunkById(500, function (Collection $fields) use ($calibrations, $alpha, &$counts): void {
                foreach ($fields as $field) {
                    $calibration = $calibrations->get($field->publisher_group.'|'.$field->script_class);
                    $decision = $this->decisionFor($field, $calibration);

                    $certified = $calibration instanceof GroupCalibration;

                    $field->forceFill([
                        'decision' => $decision,
                        // The level actually certified at, which for a small
                        // group is looser than the one asked for.
                        'decision_alpha' => $certified ? $calibration->certifiedAlpha : $alpha,
                        // Only an acceptance carries a basis: a deferral claims
                        // nothing and so has nothing to qualify.
                        'decision_basis' => $certified && $decision === self::ACCEPTED ? $calibration->basis : null,
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
