<?php

use App\Jobs\RunSourceEndpointCrawl;
use App\Models\SourceEndpoint;
use App\Models\SourcePublisher;
use App\Services\Extraction\BenchmarkEvaluationService;
use App\Services\Extraction\BornDigitalWordExtractor;
use App\Services\Extraction\CalibrationReport;
use App\Services\Extraction\CorpusLegibilityProbe;
use App\Services\Extraction\ExtractionRoutingReport;
use App\Services\Extraction\KeyValueExtractor;
use App\Services\Extraction\ReviewCandidateGenerator;
use App\Services\Ingestion\BangladeshSourceCatalogue;
use App\Services\Ingestion\SourceRegistryProvisioner;
use App\Services\Intelligence\AmendmentCountReader;
use App\Services\Intelligence\CivicIntegrityEngineService;
use App\Services\Intelligence\NoticeRevisionDetector;
use App\Services\Normalisation\OcdsTenderNormaliser;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

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

Artisan::command('civiclens:calibration-report {--alpha=0.05} {--split=test}', function (CalibrationReport $report): int {
    $alphaOption = $this->option('alpha');
    $splitOption = $this->option('split');
    $alpha = is_numeric($alphaOption) ? (float) $alphaOption : 0.0;
    $split = is_string($splitOption) && $splitOption !== '' ? $splitOption : 'test';

    if ($alpha <= 0.0 || $alpha >= 1.0) {
        $this->error('Alpha must be a number between 0 and 1 exclusive.');

        return 1;
    }

    $this->line(json_encode($report->build($alpha, $split), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    return 0;
})->purpose('Report group-conditional conformal calibration and its realized risk');

Artisan::command('civiclens:evaluate-benchmark {manifest} {--name=benchmark}', function (BenchmarkEvaluationService $service): int {
    $manifest = $this->argument('manifest');
    $name = $this->option('name');

    if (! is_string($manifest) || $manifest === '') {
        $this->error('A manifest path is required.');

        return 1;
    }

    $summary = $service->evaluate($manifest, is_string($name) && $name !== '' ? $name : 'benchmark');

    $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    return 0;
})->purpose('Evaluate a gold-annotated benchmark manifest into calibration fields');

Artisan::command('civiclens:probe-legibility {manifest} {--pages=}', function (CorpusLegibilityProbe $probe): int {
    $manifest = $this->argument('manifest');
    $pages = $this->option('pages');

    if (! is_string($manifest) || $manifest === '') {
        $this->error('A manifest path is required.');

        return 1;
    }

    $summary = $probe->probe($manifest, is_numeric($pages) ? (int) $pages : null);

    $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    return 0;
})->purpose('Measure whether the OCR engine can read a benchmark corpus at all');

Artisan::command('civiclens:generate-review-candidates {--limit=200}', function (ReviewCandidateGenerator $generator): int {
    $limit = $this->option('limit');
    $summary = $generator->generate(is_numeric($limit) ? (int) $limit : 200);

    $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    return 0;
})->purpose('Create unjudged extraction fields for a reviewer to adjudicate');

Artisan::command('civiclens:publisher-report {slug} {--save}', function (AmendmentCountReader $amendments, NoticeRevisionDetector $revisions): int {
    $slug = $this->argument('slug');
    $publisher = is_string($slug) ? SourcePublisher::query()->where('slug', $slug)->first() : null;

    if (! $publisher instanceof SourcePublisher) {
        $this->error('No publisher is registered under that slug.');

        return 1;
    }

    $revised = $revisions->detect($publisher->getKey());

    $report = [
        'publisher' => $publisher->slug,
        'attribution' => $publisher->attribution_name,
        'observed_at' => now()->toIso8601String(),
        // Both indicators count what the publisher itself stated. Neither
        // infers anything, so both work before any calibration exists.
        'amendments' => $amendments->summarise($publisher->getKey()),
        'revisions' => [
            'revised_notices' => count($revised),
            'findings' => $revised,
        ],
    ];

    $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    if ($this->option('save')) {
        // One file per publisher per day. A crawl that runs unattended for a
        // week should leave a trail someone can read afterwards, and a report
        // that only exists while a person is watching is not one.
        $path = 'reports/'.$publisher->slug.'/'.now()->toDateString().'.json';
        Storage::disk('local')->put($path, $json);
        $this->info('Wrote '.$path);

        return 0;
    }

    $this->line($json);

    return 0;
})->purpose('Report what a publisher declared and what it later changed');

Artisan::command('civiclens:normalise-notice {path} {--page=1}', function (BornDigitalWordExtractor $words, KeyValueExtractor $extractor, OcdsTenderNormaliser $normaliser): int {
    $path = $this->argument('path');
    $page = $this->option('page');

    if (! is_string($path) || ! is_readable($path)) {
        $this->error('A readable document path is required.');

        return 1;
    }

    $recognised = $words->words($path, is_numeric($page) ? (int) $page : 1);
    $fields = [];

    foreach ((array) config('civiclens.normalisation.notice_labels', []) as $label) {
        if (! is_string($label)) {
            continue;
        }

        $read = $extractor->extract($label, $recognised);

        if ($read !== null) {
            $fields[$label] = $read['value'];
        }
    }

    $this->line(json_encode(
        $normaliser->normalise($fields) + ['fields_read' => count($fields)],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    ));

    return 0;
})->purpose('Read a tender notice and report it in Open Contracting shape');

Artisan::command('civiclens:register-bangladesh-sources', function (SourceRegistryProvisioner $provisioner, BangladeshSourceCatalogue $catalogue): int {
    $results = [];

    foreach ($catalogue->definitions() as $publisher) {
        $results[] = $provisioner->provision(
            $publisher['slug'],
            $publisher['name'],
            $publisher['class'],
            $publisher['homepage'],
            $publisher['authority'],
            $publisher['authorisation'],
            $publisher['endpoints'],
        );
    }

    $this->line(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

    return 0;
})->purpose('Record the reviewed decision to fetch from authorised Bangladesh public sources');

Schedule::command('civiclens:integrity-run')
    ->dailyAt('02:15')
    ->withoutOverlapping();

// Daily rather than hourly: these counts move at the pace a publisher amends
// notices, and a snapshot per day is a readable trail rather than noise.
Schedule::command('civiclens:publisher-report bppa-egp --save')
    ->dailyAt('03:00')
    ->withoutOverlapping();

Schedule::command('civiclens:sources-dispatch')
    ->everyFiveMinutes()
    ->withoutOverlapping(4);
