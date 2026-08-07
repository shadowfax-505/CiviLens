<?php

use App\Jobs\RunSourceEndpointCrawl;
use App\Models\SourceEndpoint;
use App\Services\Extraction\ExtractionRoutingReport;
use App\Services\Intelligence\CivicIntegrityEngineService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('civiclens:integrity-run', function (CivicIntegrityEngineService $engine): int {
    $run = $engine->run();

    $this->info('Civic integrity engine run '.$run->uuid.' completed with '.$run->indicators_created.' indicators.');

    return 0;
})->purpose('Run deterministic CivicLens integrity analysis');

Artisan::command('civiclens:sources-dispatch', function (): int {
    if (! config('civiclens.ingestion.enabled')) {
        $this->comment('Governed source acquisition is disabled.');

        return 0;
    }

    $dispatched = 0;

    SourceEndpoint::query()
        ->whereNull('paused_at')
        ->whereHas('publisher', fn ($query) => $query->where('is_active', true))
        ->orderBy('id')
        ->each(function (SourceEndpoint $endpoint) use (&$dispatched): void {
            if ($endpoint->isDue()) {
                RunSourceEndpointCrawl::dispatch($endpoint->id);
                $dispatched++;
            }
        });

    $this->info("Dispatched {$dispatched} governed source crawl jobs.");

    return 0;
})->purpose('Dispatch due allowlisted source acquisition jobs');

Artisan::command('civiclens:extraction-summary', function (ExtractionRoutingReport $report): int {
    $summary = $report->build();

    if ($summary['total_pages'] === 0) {
        $this->comment('No extraction pages have been recorded yet.');

        return 0;
    }

    $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    return 0;
})->purpose('Report native versus OCR-required page routing across recorded extractions');

Schedule::command('civiclens:integrity-run')
    ->dailyAt('02:15')
    ->withoutOverlapping();

Schedule::command('civiclens:sources-dispatch')
    ->everyFiveMinutes()
    ->withoutOverlapping(4);
