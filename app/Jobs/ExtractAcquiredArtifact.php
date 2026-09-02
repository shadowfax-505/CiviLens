<?php

namespace App\Jobs;

use App\Models\ExtractionRun;
use App\Models\SourceArtifactVersion;
use App\Services\Extraction\NativeExtractionService;
use App\Services\Extraction\SelectiveOcrService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Read an artifact the crawl acquired.
 *
 * Acquisition stored artifacts and nothing read them: after two publishers and
 * ten fetched audit reports there were zero extraction runs, because the
 * extraction spine was never reachable from the pipeline. Every downstream
 * stage — routing, selective OCR, field extraction, calibration — sits behind
 * this one call.
 *
 * Native extraction first, because most published documents carry a text layer
 * and reading it is exact. OCR runs only for the pages native extraction could
 * not read, which is what SelectiveOcrService decides.
 */
class ExtractAcquiredArtifact implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * Long enough to read a scanned document, not a page.
     *
     * OCR runs per page, and the audit reports are twenty to thirty scanned
     * pages each. At the rate this corpus recognises, a single artifact takes
     * several minutes, and the default worker timeout of three minutes killed
     * one mid-run: the job failed, the artifact stayed unread, and retrying it
     * would have failed at exactly the same point every time.
     *
     * The worker must be given at least as much (--timeout), or it stops the
     * job before this applies.
     */
    public int $timeout = 1500;

    public int $uniqueFor = 3600;

    /** @var list<int> */
    public array $backoff = [60, 300];

    /**
     * Read it again even if it has already been read.
     *
     * Declared with a default rather than promoted into the constructor, and
     * that is the whole point: a queued job outlives the class that made it.
     * Promoting this left every payload already in the queue without the
     * property, and a promoted property has no default to fall back on — PHP
     * restores only what a payload carries, so reading it was a fatal error
     * rather than a false. Twenty-four extraction jobs died that way within
     * minutes of the change.
     *
     * A declared default is applied when the object is created, including by
     * unserialize, which bypasses the constructor. So an old payload reads
     * false: it was enqueued before anyone could ask for a re-read.
     */
    public bool $force = false;

    public function __construct(public readonly int $artifactId, bool $force = false)
    {
        $this->force = $force;

        $this->onQueue('ingestion');
    }

    public function uniqueId(): string
    {
        return 'extract-artifact:'.$this->artifactId;
    }

    public function handle(NativeExtractionService $native, SelectiveOcrService $ocr): void
    {
        $artifact = SourceArtifactVersion::query()->find($this->artifactId);

        if (! $artifact instanceof SourceArtifactVersion) {
            return;
        }

        // Reading a document a second time is not harmless.
        //
        // `ShouldBeUnique` stops two copies of this job coexisting for an hour,
        // and says nothing about a document read yesterday. Artifact 665 —
        // 587 pages — carried three completed runs and 1,761 stored pages,
        // exactly three copies of itself, and a fourth was in flight when this
        // was found. Every copy is a full OCR pass: hours of the one worker,
        // spent to store pages the corpus already had.
        //
        // The published counts survived it, because ExtractionPage::current()
        // reads only the latest non-failed run per artifact. So this wasted
        // days of machine time and reported nothing wrong, which is the shape
        // of defect this pipeline keeps meeting.
        //
        // A re-read is sometimes right — a routing policy changes, a text layer
        // turns out to be mojibake — so it stays available, and has to be asked
        // for.
        if (! $this->force && ExtractionRun::query()
            ->where('source_artifact_version_id', $artifact->getKey())
            ->where('status', 'completed')
            ->exists()) {
            return;
        }

        $ocr->process($native->extract($artifact));
    }
}
