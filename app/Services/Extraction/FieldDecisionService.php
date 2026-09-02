<?php

namespace App\Services\Extraction;

use App\Data\Extraction\GroupCalibration;
use App\Models\ExtractionField;
use App\Models\ExtractionRun;
use App\Models\SourceArtifactVersion;
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

        // A value a reviewer marked wrong is never accepted, whatever decision
        // it carried before they looked at it.
        //
        // Adjudicated fields are otherwise skipped here, and for a good reason:
        // deciding the calibration set from its own calibration is circular.
        // But that argument is about fitting a threshold, not about honouring
        // an explicit human rejection, and skipping them left `decision =
        // accepted` sitting on a field whose reviewer had said it was wrong.
        // Publication guards against it separately, so nothing reached the
        // site — the trap is for any report that reads the decision column and
        // reasonably believes it.
        $counts[self::DEFERRED] += ExtractionField::query()
            ->where('is_correct', false)
            ->where('decision', self::ACCEPTED)
            ->update(['decision' => self::DEFERRED, 'decision_basis' => null]);

        // A value read off a second copy of a file is not a second value.
        //
        // The same document reaches the archive from two addresses, both copies
        // are extracted, and both produce candidates — 2,373 accepted values
        // sat on 31 duplicate artifacts. Publication lists each file once and
        // so could not place them, which was right: showing both would count
        // one document's figures twice. But they stayed accepted, so the
        // certified total counted them, and the snapshot could never catch up.
        // Drift of 2,373 that no amount of publishing would clear.
        //
        // The canonical scope already governs candidate generation, the review
        // queue and the published document list. This is the fourth place, and
        // the one where it decides what the corpus claims to certify.
        $counts[self::DEFERRED] += ExtractionField::query()
            ->where('decision', self::ACCEPTED)
            ->whereIn('extraction_run_id', ExtractionRun::query()
                ->whereNotIn('source_artifact_version_id', SourceArtifactVersion::query()->canonical()->select('id'))
                ->select('id'))
            ->update(['decision' => self::DEFERRED, 'decision_basis' => null]);

        // A document read twice is not two documents.
        //
        // Artifact 665 is a 587-page judgment. It carried three completed runs,
        // each a full set of pages and fields, and the live snapshot published
        // its document with fifteen figures: eight values from one run and
        // seven of the same values from another. Corpus-wide, 9,737 accepted
        // values sat on runs that had been superseded.
        //
        // This is the duplicate-artifact rule one level down. That one is about
        // the same file arriving at two addresses; this is about the same file
        // read twice. ExtractionPage::current() has always known which run
        // counts — candidate generation, the review queue and the published
        // page list all ask it — and the decision pass was the one place that
        // never did.
        $counts[self::DEFERRED] += ExtractionField::query()
            ->where('decision', self::ACCEPTED)
            ->whereNotIn('extraction_run_id', ExtractionRun::currentIds())
            ->update(['decision' => self::DEFERRED, 'decision_basis' => null]);

        ExtractionField::query()
            // Excluded from the pass as well as deferred above it, for the same
            // reason as the duplicates: deferring first and then deciding
            // everything without a gold_source accepts them straight back.
            ->whereIn('extraction_run_id', ExtractionRun::currentIds())
            // And excluded from the pass below, not merely deferred above it.
            // Deferring first and then deciding everything without a
            // gold_source simply accepted them again — the reviewer-rejection
            // rule survives that only because an adjudicated field carries a
            // gold_source and is skipped.
            ->whereNotIn('extraction_run_id', ExtractionRun::query()
                ->whereNotIn('source_artifact_version_id', SourceArtifactVersion::query()->canonical()->select('id'))
                ->select('id'))
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
                    // The stratum, which now includes the kind of value. A
                    // field whose stratum has no calibration is deferred by the
                    // deny-by-default rule below, which is what the explicit
                    // kind check used to do before the kind became part of the
                    // key: one mechanism instead of two saying the same thing.
                    $calibration = $calibrations->get(
                        $field->publisher_group.'|'.$field->script_class.'|'.$field->field_key,
                    );
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
