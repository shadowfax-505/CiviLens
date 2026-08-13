<?php

namespace App\Jobs;

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

    public function __construct(public readonly int $artifactId)
    {
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

        $ocr->process($native->extract($artifact));
    }
}
