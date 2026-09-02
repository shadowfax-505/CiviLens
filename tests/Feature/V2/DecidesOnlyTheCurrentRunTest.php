<?php

use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Models\ExtractionRun;
use App\Models\SourceArtifactVersion;
use App\Services\Extraction\FieldDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** A calibration set big enough to certify, on the run that counts. */
function labelledOnRun(ExtractionRun $run, int $labels = 40): void
{
    $page = ExtractionPage::factory()->create([
        'extraction_run_id' => $run->id,
        'page_number' => 1,
    ]);

    foreach (range(1, $labels) as $ignored) {
        ExtractionField::factory()->create([
            'extraction_run_id' => $run->id,
            'extraction_page_id' => $page->id,
            'field_key' => 'amount',
            'publisher_group' => 'dgcivil-bangladesh',
            'script_class' => 'bn',
            'calibration_split' => 'calibration',
            'nonconformity_score' => 0.01,
            'is_correct' => true,
            'gold_value' => 'seen',
            'gold_source' => 'reviewer',
        ]);
    }
}

function candidateOnRun(ExtractionRun $run): ExtractionField
{
    // A page number of its own: one page per run already exists for the labels.
    $page = ExtractionPage::factory()->create([
        'extraction_run_id' => $run->id,
        'page_number' => ExtractionPage::query()->where('extraction_run_id', $run->id)->count() + 1,
    ]);

    return ExtractionField::factory()->create([
        'extraction_run_id' => $run->id,
        'extraction_page_id' => $page->id,
        'field_key' => 'amount',
        'publisher_group' => 'dgcivil-bangladesh',
        'script_class' => 'bn',
        'calibration_split' => null,
        'nonconformity_score' => 0.01,
        'is_correct' => null,
        'gold_source' => null,
        'decision' => FieldDecisionService::DEFERRED,
    ]);
}

it('certifies a value on the run that counts', function (): void {
    $artifact = SourceArtifactVersion::factory()->create();
    $run = ExtractionRun::factory()->create([
        'source_artifact_version_id' => $artifact->id,
        'status' => 'completed',
    ]);

    labelledOnRun($run);
    $value = candidateOnRun($run);

    app(FieldDecisionService::class)->decide(0.05);

    expect($value->refresh()->decision)->toBe(FieldDecisionService::ACCEPTED);
});

it('does not certify the same document twice when it was read twice', function (): void {
    // Artifact 665 is a 587-page judgment that carried three completed runs,
    // and the live snapshot published its document with fifteen figures: eight
    // values from one run and seven of the same values from another.
    $artifact = SourceArtifactVersion::factory()->create();

    $first = ExtractionRun::factory()->create([
        'source_artifact_version_id' => $artifact->id,
        'status' => 'completed',
    ]);

    $second = ExtractionRun::factory()->create([
        'source_artifact_version_id' => $artifact->id,
        'status' => 'completed',
    ]);

    labelledOnRun($second);

    $superseded = candidateOnRun($first);
    $current = candidateOnRun($second);

    app(FieldDecisionService::class)->decide(0.05);

    expect($current->refresh()->decision)->toBe(FieldDecisionService::ACCEPTED)
        ->and($superseded->refresh()->decision)->toBe(FieldDecisionService::DEFERRED);
});

it('takes back an acceptance on a run that has since been superseded', function (): void {
    // Deferring is not enough on its own: these were accepted before the
    // re-read existed, and nothing revisits them.
    $artifact = SourceArtifactVersion::factory()->create();

    $first = ExtractionRun::factory()->create([
        'source_artifact_version_id' => $artifact->id,
        'status' => 'completed',
    ]);

    $stale = candidateOnRun($first);
    $stale->forceFill(['decision' => FieldDecisionService::ACCEPTED, 'decision_basis' => 'stratum'])->save();

    $second = ExtractionRun::factory()->create([
        'source_artifact_version_id' => $artifact->id,
        'status' => 'completed',
    ]);

    // Labelled, so a threshold exists and this value's score is under it.
    // Without that there is no calibration at all and everything defers, and
    // the test would pass for a reason that has nothing to do with the run.
    labelledOnRun($second);

    app(FieldDecisionService::class)->decide(0.05);

    expect($stale->refresh()->decision)->toBe(FieldDecisionService::DEFERRED)
        ->and($stale->refresh()->decision_basis)->toBeNull();
});
