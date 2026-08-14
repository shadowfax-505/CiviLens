<?php

namespace App\Jobs;

use App\Models\ExtractionPage;
use App\Services\Extraction\PageTableAssembler;
use App\Services\Extraction\TableStructureDetector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Recover one page's table structure.
 *
 * Deliberately not in the extraction path. Detection takes about ninety seconds
 * a page, and most pages are prose, so running it inline would make acquisition
 * unusable to gain nothing on the majority of pages.
 */
class ExtractPageTables implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $uniqueFor = 7200;

    /** Ninety seconds a page, and a page can be slower than the average. */
    public int $timeout = 900;

    public function __construct(public readonly int $pageId)
    {
        $this->onQueue('ingestion');
    }

    public function uniqueId(): string
    {
        return 'page-tables:'.$this->pageId;
    }

    public function handle(PageTableAssembler $assembler, TableStructureDetector $detector): void
    {
        // Without the sidecar a page simply carries no table structure. Failing
        // here would fill the failed queue on every machine that has not
        // installed a gigabyte of models to do optional work.
        if (! $detector->isAvailable()) {
            return;
        }

        $page = ExtractionPage::query()->find($this->pageId);

        if ($page instanceof ExtractionPage) {
            $assembler->assemble($page);
        }
    }
}
