<?php

use App\Jobs\ExtractAcquiredArtifact;
use App\Models\ExtractionRun;
use App\Models\SourceArtifactVersion;
use App\Services\Extraction\PipelineHealthReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function attemptedArtifact(string $status, ?string $reason = null): SourceArtifactVersion
{
    $artifact = SourceArtifactVersion::factory()->create(['is_quarantined' => false]);

    ExtractionRun::factory()->create([
        'source_artifact_version_id' => $artifact->id,
        'status' => $status,
        'failure_reason' => $reason,
    ]);

    return $artifact;
}

/**
 * Queue a real payload without running it.
 *
 * The test queue is `sync`, so a plain dispatch executes the extraction there
 * and then — which fails on a factory artifact whose bytes were never stored.
 * The check reads the `jobs` table, so faking the queue would hide the very
 * row it is looking for; the database driver is what actually queues one.
 */
function queueExtraction(int $artifactId): void
{
    config(['queue.default' => 'database']);

    ExtractAcquiredArtifact::dispatch($artifactId);
}

function stranded(): array
{
    return app(PipelineHealthReport::class)->build()['checks']['stranded_documents'];
}

it('counts a document whose every reading failed and nothing is queued to read', function (): void {
    // Artifact 129 is a 672-page judgment refused four times in August for
    // exceeding a 500-page limit. The limit is 2,000 now and would admit it,
    // but its last attempt was two weeks ago and there is no job for it, so it
    // is simply absent from the corpus — which the export noticed as
    // held_but_never_read and this report did not notice at all.
    attemptedArtifact('failed', 'Document exceeds the configured page limit.');

    expect(stranded())
        ->stranded->toBe(1)
        ->reasons->toBe(['Document exceeds the configured page limit.' => 1]);
});

it('does not count a document that has been read', function (): void {
    attemptedArtifact('completed');

    expect(stranded()['stranded'])->toBe(0);
});

it('does not count a document a job is already waiting to read', function (): void {
    // The distinction that makes the number actionable: a failure with a job
    // behind it is in progress, and one without is lost.
    $artifact = attemptedArtifact('failed', 'Selective OCR failed.');

    queueExtraction((int) $artifact->id);

    expect(stranded()['stranded'])->toBe(0);
});

it('does not count a document nothing has ever attempted', function (): void {
    // An artifact awaiting its first reading is the normal state of a corpus
    // being ingested, not a loss. Counting it would make the number rise every
    // time a crawl succeeded, which is the opposite of a signal.
    SourceArtifactVersion::factory()->create(['is_quarantined' => false]);

    expect(stranded()['stranded'])->toBe(0);
});

it('does not count a quarantined document', function (): void {
    // Its bytes failed a malware scan and were never trusted enough to read.
    // That is a refusal, not a document the pipeline lost.
    $artifact = SourceArtifactVersion::factory()->create(['is_quarantined' => true]);

    ExtractionRun::factory()->create([
        'source_artifact_version_id' => $artifact->id,
        'status' => 'failed',
        'failure_reason' => 'Quarantined artifacts are not eligible for extraction.',
    ]);

    expect(stranded()['stranded'])->toBe(0);
});

it('reports rather than failing health', function (): void {
    // Nothing is broken: a document that genuinely cannot be read will sit here
    // for ever, and failing the gate on it would block every publication until
    // somebody deleted a row. A count that rises is the signal.
    attemptedArtifact('failed', 'Native extraction could not read the document.');

    $report = app(PipelineHealthReport::class)->build();

    expect($report['checks']['stranded_documents']['ok'])->toBeTrue()
        ->and($report['failing'])->not->toContain('stranded_documents');
});

it('reads the artifact a queued job names, not merely that a job exists', function (): void {
    // A job for some other document is not a job for this one, and the payload
    // is a blob with no column to join on.
    $lost = attemptedArtifact('failed', 'Selective OCR failed.');
    $other = SourceArtifactVersion::factory()->create(['is_quarantined' => false]);

    queueExtraction((int) $other->id);

    expect(DB::table('jobs')->count())->toBe(1)
        ->and(stranded()['stranded'])->toBe(1)
        ->and($lost->fresh())->not->toBeNull();
});
