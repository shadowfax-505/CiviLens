<?php

use App\Jobs\RunSourceEndpointCrawl;
use App\Models\SourceEndpoint;
use App\Services\Extraction\BenchmarkEvaluationService;
use App\Services\Extraction\CalibrationReport;
use App\Services\Extraction\CorpusLegibilityProbe;
use App\Services\Extraction\ExtractionRoutingReport;
use App\Services\Ingestion\SourceRegistryProvisioner;
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

Artisan::command('civiclens:register-bangladesh-sources', function (SourceRegistryProvisioner $provisioner): int {
    $results = [];

    $results[] = $provisioner->provision(
        'bppa-egp',
        'Bangladesh Public Procurement Authority (e-GP)',
        'government',
        'https://www.eprocure.gov.bd/',
        'Bangladesh Public Procurement Authority',
        'I authorise CivicLens to fetch public tender notices from eprocure.gov.bd at a rate-limited pace.',
        [[
            'name' => 'Public tender and proposal listing',
            'connector_type' => 'static_html',
            'base_url' => 'https://www.eprocure.gov.bd/TenderDetailsServlet',
            'allowed_hosts' => ['www.eprocure.gov.bd'],
            'allowed_path_prefixes' => ['/TenderDetailsServlet'],
            'access_decision' => 'operator-authorised-public-notices',
            'rate_limit_per_minute' => 6,
        ]],
    );

    $results[] = $provisioner->provision(
        'cag-bangladesh',
        'Comptroller and Auditor General of Bangladesh',
        'government',
        'https://cag.org.bd/',
        'Office of the Comptroller and Auditor General of Bangladesh',
        'I authorise CivicLens to fetch public audit reports from cag.org.bd rate-limited.',
        [
            [
                // The audit category pages return placeholder text, so the
                // storage path is the route that actually carries documents.
                'name' => 'Published audit document storage',
                'connector_type' => 'direct_download',
                'base_url' => 'https://cag.org.bd/storage/app/uploads/public/',
                'allowed_hosts' => ['cag.org.bd'],
                'allowed_path_prefixes' => ['/storage/app/uploads/public', '/storage/app/media'],
                'access_decision' => 'operator-authorised-public-audit',
                'rate_limit_per_minute' => 4,
            ],
            [
                'name' => 'Civil Audit Directorate report archive',
                'connector_type' => 'static_html',
                'base_url' => 'https://dgcivil-cagbd.org/audit-report/',
                'allowed_hosts' => ['dgcivil-cagbd.org'],
                'allowed_path_prefixes' => ['/audit-report', '/wp-content/uploads'],
                'access_decision' => 'operator-authorised-public-audit',
                'rate_limit_per_minute' => 4,
            ],
        ],
    );

    $this->line(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

    return 0;
})->purpose('Record the reviewed decision to fetch from authorised Bangladesh public sources');

Schedule::command('civiclens:integrity-run')
    ->dailyAt('02:15')
    ->withoutOverlapping();

Schedule::command('civiclens:sources-dispatch')
    ->everyFiveMinutes()
    ->withoutOverlapping(4);
