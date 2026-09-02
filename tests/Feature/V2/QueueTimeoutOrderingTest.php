<?php

use App\Jobs\ExtractAcquiredArtifact;
use App\Jobs\ExtractPageTables;
use App\Models\ExtractionPage;
use App\Models\ExtractionRun;
use App\Services\Extraction\NativeExtractionService;
use App\Services\Extraction\SelectiveOcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** The `--timeout=N` the launchd worker is started with. */
function workerTimeout(): int
{
    $plist = file_get_contents(base_path('ops/launchd/com.civiclens.queue-worker.plist'));

    expect($plist)->toBeString();

    preg_match('/--timeout=(\d+)/', (string) $plist, $matches);

    return (int) ($matches[1] ?? 0);
}

it('gives the worker at least as long as the longest job asks for', function (): void {
    // ExtractAcquiredArtifact says in its own docblock that the worker must be
    // given at least as much as its timeout or the job is stopped before that
    // applies. The worker was started with 900 against a job asking 1500, so a
    // 587-page document was killed at fifteen minutes, every time, for ever.
    $longest = max(
        (new ExtractAcquiredArtifact(1))->timeout,
        (new ExtractPageTables(1))->timeout,
    );

    expect(workerTimeout())->toBeGreaterThanOrEqual($longest);
});

it('holds a reservation longer than the worker will run a job', function (): void {
    // A reservation reclaimed while its job is still running dispatches that
    // job again. One document read three times, 1,761 stored pages for 587
    // real ones, and nothing in the health output said so.
    expect((int) config('queue.connections.database.retry_after'))
        ->toBeGreaterThan(workerTimeout());
});

it('does not read a document that has already been read', function (): void {
    $run = ExtractionRun::factory()->create(['status' => 'completed']);

    ExtractionPage::factory()->create(['extraction_run_id' => $run->id]);

    $native = Mockery::mock(NativeExtractionService::class);
    $native->shouldNotReceive('extract');

    (new ExtractAcquiredArtifact((int) $run->source_artifact_version_id))
        ->handle($native, Mockery::mock(SelectiveOcrService::class));
});

it('reads it again when asked to', function (): void {
    // A re-read is sometimes right — a routing policy changes, a text layer
    // turns out to be mojibake — so it stays available and has to be asked for.
    $run = ExtractionRun::factory()->create(['status' => 'completed']);

    $native = Mockery::mock(NativeExtractionService::class);
    $native->shouldReceive('extract')->once()->andReturn($run);

    $ocr = Mockery::mock(SelectiveOcrService::class);
    $ocr->shouldReceive('process')->once();

    (new ExtractAcquiredArtifact((int) $run->source_artifact_version_id, force: true))
        ->handle($native, $ocr);
});
