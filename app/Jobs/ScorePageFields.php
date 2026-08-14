<?php

namespace App\Jobs;

use App\Models\ExtractionPage;
use App\Services\Extraction\SecondReadScorer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ScorePageFields implements ShouldQueue
{
    use Queueable;

    /** A page of many fields is many crops and many recognizer calls. */
    public int $timeout = 900;

    public function __construct(private readonly int $pageId)
    {
        $this->onQueue('ingestion');
    }

    public function handle(SecondReadScorer $scorer): void
    {
        $page = ExtractionPage::query()->find($this->pageId);

        if ($page instanceof ExtractionPage) {
            $scorer->scorePage($page);
        }
    }
}
