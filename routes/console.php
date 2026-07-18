<?php

use App\Jobs\ReindexSearchRegistry;
use App\Models\SearchIndex;
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

Schedule::command('civiclens:integrity-run')
    ->dailyAt('02:15')
    ->withoutOverlapping();

Artisan::command('civiclens:search-reindex', function (): int {
    ReindexSearchRegistry::dispatchSync();

    $this->info('Search reindex complete. '.SearchIndex::query()->count().' records indexed.');

    return 0;
})->purpose('Rebuild the universal search index for every registered searchable model');
