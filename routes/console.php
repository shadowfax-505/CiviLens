<?php

use App\Jobs\ExtractPageTables;
use App\Jobs\RunSourceEndpointCrawl;
use App\Jobs\ScorePageFields;
use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Models\ExtractionRun;
use App\Models\PublicationFigure;
use App\Models\ScreeningEntity;
use App\Models\SourceArtifactVersion;
use App\Models\SourceEndpoint;
use App\Models\SourcePublisher;
use App\Services\Extraction\BenchmarkEvaluationService;
use App\Services\Extraction\BengaliTextPlausibility;
use App\Services\Extraction\BornDigitalWordExtractor;
use App\Services\Extraction\CalibrationReport;
use App\Services\Extraction\CorpusLegibilityProbe;
use App\Services\Extraction\ExtractionRoutingReport;
use App\Services\Extraction\FieldDecisionService;
use App\Services\Extraction\KeyValueExtractor;
use App\Services\Extraction\PageRoutingPolicy;
use App\Services\Extraction\PaperReport;
use App\Services\Extraction\PipelineHealthReport;
use App\Services\Extraction\ReviewCandidateGenerator;
use App\Services\Extraction\ScoreDiscriminationReport;
use App\Services\Extraction\SecondReadScorer;
use App\Services\Extraction\SelectiveOcrService;
use App\Services\Extraction\TableStructureDetector;
use App\Services\Extraction\WordGeometryBackfill;
use App\Services\Ingestion\ArchivedPageLinkHarvester;
use App\Services\Ingestion\BangladeshSourceCatalogue;
use App\Services\Ingestion\CertificateChainRepair;
use App\Services\Ingestion\SourceCandidateVerifier;
use App\Services\Ingestion\SourceRegistryProvisioner;
use App\Services\Ingestion\SourceYieldReport;
use App\Services\Intelligence\AmendmentCountReader;
use App\Services\Intelligence\CivicIntegrityEngineService;
use App\Services\Intelligence\NoticeRevisionDetector;
use App\Services\Normalisation\OcdsTenderNormaliser;
use App\Services\Publication\PublicationSnapshotBuilder;
use App\Services\Screening\EntityScreener;
use App\Services\Screening\ScreeningEntityLoader;
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

Artisan::command('civiclens:sources-dispatch {--endpoint=* : Crawl only these endpoint ids} {--force : Crawl whether or not the interval is due}', function (): int {
    if (! config('civiclens.ingestion.enabled')) {
        $this->comment('Governed source acquisition is disabled.');

        return 0;
    }

    // The schedule runs this every five minutes and wants only what is due. An
    // operator running it by hand has usually just fixed the reason a source
    // failed and wants that source now, not at the top of its next interval.
    $only = array_map('intval', (array) $this->option('endpoint'));
    $force = (bool) $this->option('force');

    $dispatched = 0;

    SourceEndpoint::query()
        ->whereNull('paused_at')
        ->when($only !== [], fn ($query) => $query->whereIn('id', $only))
        ->whereHas('publisher', fn ($query) => $query->where('is_active', true))
        ->orderBy('id')
        ->each(function (SourceEndpoint $endpoint) use (&$dispatched, $force): void {
            if ($force || $endpoint->isDue()) {
                RunSourceEndpointCrawl::dispatch($endpoint->id);
                $dispatched++;
            }
        });

    $this->info("Dispatched {$dispatched} governed source crawl jobs.");

    return 0;
})->purpose('Dispatch due allowlisted source acquisition jobs');

Artisan::command('civiclens:publish {label} {--notes=} {--publish : Make this snapshot the one the public site serves}', function (PublicationSnapshotBuilder $builder): int {
    $label = $this->argument('label');
    $notes = $this->option('notes');

    $snapshot = $builder->build(is_string($label) ? $label : '', is_string($notes) ? $notes : null);

    $this->info("Built snapshot {$snapshot->label}.");
    $this->table(
        ['documents', 'figures', 'notices', 'figures skipped', 'requested alpha'],
        [[
            $snapshot->document_count,
            $snapshot->figure_count,
            $snapshot->notice_count,
            $snapshot->skipped_figure_count,
            $snapshot->requested_alpha,
        ]],
    );

    if ($snapshot->skipped_figure_count > 0) {
        $this->warn("{$snapshot->skipped_figure_count} certified values could not be traced to a document and were left out.");
    }

    // Building and publishing are separate on purpose: a snapshot can be
    // inspected before anyone sees it.
    if ($this->option('publish')) {
        $builder->publish($snapshot);
        $this->info('Published. This is now the snapshot the public site serves.');
    } else {
        $this->comment('Not published. Re-run with --publish, or publish it from the admin side.');
    }

    return 0;
})->purpose('Freeze the certified corpus into a publishable snapshot');

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

Artisan::command('civiclens:extract-tables {--limit=250} {--all : Include pages carrying no review candidates} {--again : Examine pages already examined}', function (TableStructureDetector $detector): int {
    if (! $detector->isAvailable()) {
        $this->error('Table structure detection is not configured on this machine.');

        return 1;
    }

    $limit = $this->option('limit');
    $limit = is_numeric($limit) ? (int) $limit : 250;

    // Pages carrying review candidates first: those are the ones a reviewer is
    // about to look at, and at ninety seconds a page the whole corpus is thirty
    // hours of work for structure most pages do not have.
    $query = ExtractionPage::query()->whereNotNull('recognized_words');

    if (! $this->option('all')) {
        // Pages a reviewer is about to look at, first. Detection is the slowest
        // thing this project runs and most pages hold no table, so the order
        // matters more than the coverage.
        $query->whereIn('id', ExtractionField::query()->whereNull('gold_source')->select('extraction_page_id'));
    }

    // A page already looked at is not worth looking at again, and this holds
    // even under `--all`: the two flags answer different questions, and folding
    // them together is what made `--all` mean "spend another twelve hours".
    // The test is "has this been attempted", not "does this have cells" — most
    // pages have no table, so testing for cells re-derived every negative on
    // every run, about 297 of the 451 pages detected in one night.
    //
    // `--again` is for after a page has been re-read and its word geometry has
    // changed underneath the old grid.
    if (! $this->option('again')) {
        $query->whereNull('tables_detected_at');
    }

    $dispatched = 0;

    $query->orderBy('id')->limit(max(1, $limit))->each(function (ExtractionPage $page) use (&$dispatched): void {
        ExtractPageTables::dispatch($page->getKey());
        $dispatched++;
    });

    $this->info("Queued {$dispatched} pages for table structure detection.");

    return 0;
})->purpose('Queue table structure detection for pages a reviewer will see');

Artisan::command('civiclens:backfill-word-geometry {--limit=250} {--all}', function (WordGeometryBackfill $backfill): int {
    $limit = $this->option('limit');
    $limit = is_numeric($limit) ? (int) $limit : 250;

    // Extraction discarded word boxes until they were persisted, so pages read
    // before that carry text no table cell can be matched against. This re-reads
    // them for geometry alone and leaves their text untouched.
    $summary = $backfill->backfill($limit, ! $this->option('all'));

    $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    return 0;
})->purpose('Recover word geometry for pages extracted before it was stored');

Artisan::command('civiclens:load-screening-entities {--dataset=worldbank_debarred}', function (ScreeningEntityLoader $loader): int {
    $dataset = $this->option('dataset');
    $dataset = is_string($dataset) && $dataset !== '' ? $dataset : 'worldbank_debarred';

    $artifact = SourceArtifactVersion::query()
        ->where('media_type', 'text/csv')
        ->latest('id')
        ->first();

    if (! $artifact instanceof SourceArtifactVersion) {
        $this->error('No screening list has been acquired yet.');

        return 1;
    }

    $summary = $loader->loadCsv($artifact, $dataset);
    $summary['bangladesh_linked'] = ScreeningEntity::query()
        ->where('dataset', $dataset)
        ->where('countries', 'like', '%bd%')
        ->count();

    $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    return 0;
})->purpose('Parse an acquired screening list into entities that can be queried');

Artisan::command('civiclens:screen {name}', function (EntityScreener $screener): int {
    $name = $this->argument('name');

    if (! is_string($name) || trim($name) === '') {
        $this->error('A name is required.');

        return 1;
    }

    // What comes back is evidence about a string, never an identification. A
    // name matching a debarred entity is a fact about the name; whether it is
    // the same organisation is a judgement someone makes with both records in
    // front of them.
    $candidates = $screener->candidates($name)->map(fn (array $hit): array => [
        'name_as_published' => $hit['entity']->name,
        'dataset' => $hit['entity']->dataset,
        'countries' => $hit['entity']->countries,
        'score' => $hit['score'],
        'method' => $hit['method'],
    ])->all();

    $this->line(json_encode([
        'queried' => $name,
        'candidates' => $candidates,
        'note' => 'A name match is evidence about a name, not an identification of an organisation.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

    return 0;
})->purpose('Check a name against the acquired screening lists');

Artisan::command('civiclens:health', function (PipelineHealthReport $health): int {
    // Table detection was dead for days because its Python environment sat under
    // /tmp and the operating system removed it. Everything here is optional at
    // runtime and fails closed, which is right and also silent: pages simply
    // stop gaining tables, and that looks exactly like a corpus without any.
    $report = $health->build();
    $report['table_detection'] = $health->tableDetection();

    $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

    return $report['healthy'] === true ? 0 : 1;
})->purpose('Check that every part of the pipeline can still do its job');

Artisan::command('civiclens:paper-report {--alpha=0.05} {--save}', function (PaperReport $report): int {
    $alpha = $this->option('alpha');
    $alpha = is_numeric($alpha) ? (float) $alpha : 0.05;

    // Regenerated rather than quoted. A figure copied into a draft cannot be
    // checked and goes stale without anyone noticing, and the unflattering ones
    // — abstention, unlocatable values, a single-publisher corpus — are exactly
    // the ones a copied figure quietly loses.
    $json = json_encode($report->build($alpha), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    if ($this->option('save')) {
        $path = 'reports/paper/'.now()->toDateString().'.json';
        Storage::disk('local')->put($path, $json);
        $this->info('Wrote '.$path);

        return 0;
    }

    $this->line($json);

    return 0;
})->purpose('Regenerate every number a write-up needs, including the unflattering ones');

Artisan::command('civiclens:decide-fields {--alpha=0.05}', function (FieldDecisionService $decisions): int {
    $alpha = $this->option('alpha');
    $alpha = is_numeric($alpha) ? (float) $alpha : 0.0;

    if ($alpha <= 0.0 || $alpha >= 1.0) {
        $this->error('Alpha must be a number between 0 and 1 exclusive.');

        return 1;
    }

    // Nothing consumed the bound before this: every extracted value sat pending
    // while the calibration it was entitled to went unapplied.
    $counts = $decisions->decide($alpha);

    $this->line(json_encode($counts, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    return 0;
})->purpose('Apply the certified thresholds to extracted values, deferring anything uncertified');

Artisan::command('civiclens:reroute-pages {--apply : Re-read them; without this it only reports}', function (BengaliTextPlausibility $plausibility, SelectiveOcrService $ocr): int {
    // A page read before the mojibake check existed kept its text layer and the
    // path `native`, and the OCR service only revisits pages already marked as
    // needing OCR. So these sit in the corpus decoding to the wrong characters,
    // excluded from review — correctly, since a reviewer would mark nine in ten
    // wrong — and contributing nothing.
    //
    // Re-applying the current routing policy to what is already stored is
    // enough to find them: the policy would send this text to OCR today.
    $stale = [];
    $skippedWithFields = 0;

    ExtractionPage::query()
        ->where('extraction_path', PageRoutingPolicy::NATIVE)
        ->whereNotNull('extracted_text')
        ->orderBy('id')
        ->chunkById(200, function ($pages) use ($plausibility, &$stale, &$skippedWithFields): void {
            foreach ($pages as $page) {
                if (! $plausibility->isImplausible((string) $page->extracted_text)) {
                    continue;
                }

                // A page someone has already reviewed, or that produced a
                // certified value, keeps the text those judgements were made
                // about. Re-reading it would leave a label describing characters
                // that are no longer there.
                if (ExtractionField::query()->where('extraction_page_id', $page->getKey())->exists()) {
                    $skippedWithFields++;

                    continue;
                }

                $stale[] = $page;
            }
        });

    $runs = collect($stale)->pluck('extraction_run_id')->unique()->values();

    $this->info(count($stale).' pages hold a text layer the routing policy would send to OCR today.');
    $this->line("  across {$runs->count()} extraction runs.");
    $this->line("  {$skippedWithFields} left alone because candidates already rest on their text.");

    if (! $this->option('apply')) {
        $this->comment('Nothing changed. Re-run with --apply.');

        return 0;
    }

    foreach ($stale as $page) {
        $page->forceFill(['extraction_path' => PageRoutingPolicy::OCR_REQUIRED])->save();
    }

    $failed = 0;

    foreach ($runs as $runId) {
        $run = ExtractionRun::query()->find($runId);

        if (! $run instanceof ExtractionRun) {
            continue;
        }

        try {
            $ocr->process($run);
            $this->line("  re-read run {$runId}");
        } catch (Throwable $exception) {
            $failed++;
            $this->warn("  run {$runId} failed: ".$exception->getMessage());
        }
    }

    $this->info('Re-read '.($runs->count() - $failed).' runs.');

    return 0;
})->purpose('Re-read pages whose stored text layer the routing policy would now reject');

Artisan::command('civiclens:prune-candidates {--apply : Delete them; without this it only reports}', function (): int {
    // A candidate the current pattern would not produce is one the page does
    // not contain — a fragment left behind when the pattern was fixed. Asking a
    // reviewer whether ৪৫,৩৯৫ matches a page reading ৬.০২,৪৫,৩৯৫ is asking
    // about a figure that is not there, so these are cleared out rather than
    // left in the queue.
    $pattern = ReviewCandidateGenerator::amountPattern();

    $stale = [];
    $keptAdjudicated = 0;
    $keptAccepted = 0;

    ExtractionPage::query()
        ->whereIn('id', ExtractionField::query()->select('extraction_page_id'))
        ->orderBy('id')
        ->chunkById(200, function ($pages) use ($pattern, &$stale, &$keptAdjudicated, &$keptAccepted): void {
            foreach ($pages as $page) {
                preg_match_all($pattern, (string) $page->extracted_text, $matches);
                $produced = array_flip($matches[0]);

                $fields = ExtractionField::query()
                    ->where('extraction_page_id', $page->getKey())
                    // A cell-derived candidate came from a table cell, not from
                    // this text, so the pattern says nothing about it.
                    ->whereNull('extraction_table_cell_id')
                    ->where('field_key', 'amount')
                    ->get();

                foreach ($fields as $field) {
                    if (isset($produced[(string) $field->extracted_value])) {
                        continue;
                    }

                    // A judgement is evidence about the system, and two of
                    // these were reviewers correctly marking a fragment wrong.
                    // Deleting a label would change the bound without saying so.
                    if ($field->gold_source !== null || $field->is_correct !== null) {
                        $keptAdjudicated++;

                        continue;
                    }

                    // Nothing published is ever removed here, and "published"
                    // means any snapshot, not only the current one. A value
                    // accepted under an earlier calibration and deferred under
                    // this one is still pinned in the snapshot that published
                    // it, and deleting it would break a citation someone may
                    // already have made. The database's own foreign key says
                    // the same thing; this makes the reason explicit rather
                    // than letting the constraint be met as a surprise.
                    if ($field->decision === 'accepted' || PublicationFigure::query()->where('extraction_field_id', $field->getKey())->exists()) {
                        $keptAccepted++;

                        continue;
                    }

                    $stale[] = (int) $field->getKey();
                }
            }
        });

    $this->info(count($stale).' candidates are no longer produced by the amount pattern.');
    $this->line("  {$keptAdjudicated} kept because a reviewer judged them.");
    $this->line("  {$keptAccepted} kept because they are accepted or already published in a snapshot.");

    if (! $this->option('apply')) {
        $this->comment('Nothing deleted. Re-run with --apply.');

        return 0;
    }

    $deleted = ExtractionField::query()->whereIn('id', $stale)->delete();

    $this->info("Deleted {$deleted} candidates.");

    return 0;
})->purpose('Remove review candidates the current amount pattern would not produce');

Artisan::command('civiclens:score-fields {--limit=500} {--labelled} {--sync : Score in this process instead of queueing}', function (SecondReadScorer $scorer): int {
    $limit = $this->option('limit');
    $limit = is_numeric($limit) ? (int) $limit : 500;

    // Queued per page: one raster serves every field on it, which is the
    // difference between forty minutes over the corpus and several hours.
    $query = ExtractionPage::query()
        ->whereIn('id', ExtractionField::query()->select('extraction_page_id'));

    if ($this->option('labelled')) {
        // Scoring the judged fields first is what makes the gate measurable
        // before the whole corpus is committed to.
        $query->whereIn('id', ExtractionField::query()->whereNotNull('is_correct')->select('extraction_page_id'));
    }

    $pages = $query->orderBy('id')->limit(max(1, $limit))->get();

    // Scoring shares one queue with the crawlers, and a crawler rate-limited to
    // a request a minute holds it for hours. Re-scoring after an extraction
    // change is not something to leave behind that, so it can be run here.
    if ($this->option('sync')) {
        $scored = 0;
        $unlocatable = 0;

        $done = 0;
        $total = $pages->count();

        foreach ($pages as $page) {
            $result = $scorer->scorePage($page);
            $scored += $result['scored'];
            $unlocatable += $result['unlocatable'];
            $done++;

            if ($done % 50 === 0 || $done === $total) {
                $this->line("  {$done}/{$total} pages");
            }
        }

        $this->info("Scored {$scored} values; {$unlocatable} could not be located and defer by rule.");

        return 0;
    }

    $dispatched = 0;

    $pages->each(function (ExtractionPage $page) use (&$dispatched): void {
        ScorePageFields::dispatch($page->getKey());
        $dispatched++;
    });

    $this->info("Queued {$dispatched} pages for second-read scoring.");

    return 0;
})->purpose('Re-read each extracted value and score it by whether the two readings agree');

Artisan::command('civiclens:score-discrimination', function (ScoreDiscriminationReport $report): int {
    // A bound holds for any score, so validity says nothing about whether the
    // score is worth thresholding. This is the number that does.
    $this->line(json_encode($report->build(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    return 0;
})->purpose('Report how well the nonconformity score separates wrong readings from right ones');

Artisan::command('civiclens:generate-review-candidates {--limit=200} {--from-cells}', function (ReviewCandidateGenerator $generator): int {
    $limit = $this->option('limit');
    $limit = is_numeric($limit) ? (int) $limit : 200;

    // Cell-derived candidates carry the row and column a figure sat in, which
    // is what a reviewer needs to place it and what any later analysis depends
    // on. Flat-page candidates carry neither and remain for pages with no table.
    $summary = $this->option('from-cells')
        ? $generator->generateFromCells($limit)
        : $generator->generate($limit);

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

Artisan::command('civiclens:build-ca-bundle {hosts?*} {--out=}', function (CertificateChainRepair $repair): int {
    $given = $this->argument('hosts');
    $candidates = is_array($given) && $given !== []
        ? $given
        : SourceEndpoint::query()->pluck('allowed_hosts')->flatten()->unique()->all();

    // Built by hand: a filtered collection is still keyed, and the repair takes
    // a list.
    $hosts = [];

    foreach ($candidates as $candidate) {
        if (is_string($candidate) && $candidate !== '') {
            $hosts[] = $candidate;
        }
    }

    $out = $this->option('out');
    $path = is_string($out) && $out !== '' ? $out : storage_path('app/ca/civiclens-ca.pem');

    // Several publishers serve only their leaf certificate. A browser hides it
    // by fetching the intermediate the leaf points at; OpenSSL does not, so the
    // site looks healthy to a person and unverifiable here. This fetches those
    // intermediates and writes them beside the system roots, which is the
    // opposite of turning verification off.
    $result = $repair->buildBundle($hosts, $path);
    $result['verifies'] = collect($hosts)
        ->mapWithKeys(fn (string $host): array => [$host => $repair->verifies($host, $path)])
        ->all();

    $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $this->info('Set INGESTION_CA_BUNDLE='.$path.' to use it.');

    return 0;
})->purpose('Fetch the intermediate certificates publishers omit, so their chains verify');

Artisan::command('civiclens:harvest-links {--apply : Record them; without this it only reports} {--limit=5000}', function (ArchivedPageLinkHarvester $harvester): int {
    $limit = $this->option('limit');

    $result = $harvester->harvest(
        apply: (bool) $this->option('apply'),
        limit: is_numeric($limit) ? (int) $limit : 5000,
    );

    $this->table(
        ['pages read', 'links found', 'new and allowed', 'refused by the allowlist', 'already known'],
        [[$result['scanned'], $result['proposed'], $result['recorded'], $result['refused'], $result['already_known']]],
    );

    if (! $this->option('apply')) {
        $this->comment('Nothing recorded. Re-run with --apply.');
    } else {
        $this->info('Recorded as discovered resources. Acquisition fetches them, with every guard it always applies.');
    }

    return 0;
})->purpose('Find documents linked from pages already in the archive');

Artisan::command('civiclens:sources-report {--json}', function (SourceYieldReport $report): int {
    $built = $report->build();

    if ($this->option('json')) {
        $this->line(json_encode($built, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return 0;
    }

    $this->table(
        ['id', 'publisher', 'docs', 'archived', 'health', 'what it produced'],
        array_map(fn (array $row): array => [
            $row['id'],
            $row['publisher'],
            $row['documents'],
            $row['artifacts_archived'],
            $row['paused'] ? 'paused' : $row['health'],
            $row['verdict'],
        ], $built['endpoints']),
    );

    // The gate for M1: an endpoint that produced nothing must say why. One that
    // produced nothing and has no failure against it is the case ADR-020 warns
    // about, where an unnoticed breakage passes for a governance decision.
    if ($built['unexplained'] !== []) {
        $this->warn(count($built['unexplained']).' endpoints produced nothing and recorded no failure:');

        foreach ($built['unexplained'] as $row) {
            $this->line("  {$row['publisher']} — {$row['name']}");
        }

        return 1;
    }

    $this->info('Every endpoint has either something to show or a reason it has not.');

    return 0;
})->purpose('Report what every registered endpoint produced, and why it produced nothing');

Artisan::command('civiclens:verify-sources {url?} {--hosts=} {--prefix=/}', function (SourceCandidateVerifier $verifier, BangladeshSourceCatalogue $catalogue): int {
    $single = $this->argument('url');

    // Read-only. It resolves, reads robots, fetches the listing and counts what
    // the discovery parser would find. A list of sources gathered by hand goes
    // stale, and registering a dead host is how an endpoint ends up looking like
    // a permission failure rather than a moved page.
    if (is_string($single) && $single !== '') {
        $hosts = $this->option('hosts');
        $hosts = is_string($hosts) && $hosts !== '' ? explode(',', $hosts) : [];
        $prefix = $this->option('prefix');

        $this->line(json_encode(
            $verifier->verify($single, $hosts, is_string($prefix) && $prefix !== '' ? $prefix : '/'),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));

        return 0;
    }

    $results = [];

    foreach ($catalogue->definitions() as $publisher) {
        foreach ($publisher['endpoints'] as $endpoint) {
            $results[] = ['publisher' => $publisher['slug']] + $verifier->verify(
                $endpoint['base_url'],
                $endpoint['allowed_hosts'],
                (string) ($endpoint['allowed_path_prefixes'][0] ?? '/'),
            );
        }
    }

    $this->line(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

    return 0;
})->purpose('Check whether a candidate source resolves, permits crawling, and publishes documents');

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

// After scores exist and calibration has moved, decisions are stale. Daily is
// the pace labels arrive at.
// Daily, so a part of the pipeline that has quietly stopped working is noticed
// within a day rather than whenever somebody happens to look.
Schedule::command('civiclens:health')
    ->dailyAt('06:00')
    ->withoutOverlapping();

Schedule::command('civiclens:decide-fields')
    ->dailyAt('04:00')
    ->withoutOverlapping();

Schedule::command('civiclens:sources-dispatch')
    ->everyFiveMinutes()
    ->withoutOverlapping(4);
