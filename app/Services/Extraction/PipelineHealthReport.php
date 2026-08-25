<?php

namespace App\Services\Extraction;

use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Models\PublicationSnapshot;
use App\Models\SourceEndpoint;
use Illuminate\Support\Facades\DB;

/**
 * Ask every part of the pipeline whether it can still do its job.
 *
 * Table detection was dead for days and nothing said so. Its Python environment
 * had been installed under /tmp, macOS purged it, and every job afterwards
 * failed with "No module named 'paddleocr'" in a queue nobody was watching. The
 * pages simply stopped gaining tables, which looks exactly like a corpus that
 * has no tables.
 *
 * Silent degradation is the failure mode this project is most exposed to,
 * because almost everything here is optional at runtime: a missing binary, an
 * unconfigured renderer or a purged virtualenv all fail closed and quietly. That
 * is the right behaviour and it needs something that notices.
 */
class PipelineHealthReport
{
    public function __construct(
        private readonly TableStructureDetector $tables,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $checks = [
            'tesseract' => $this->binary((string) config('civiclens.extraction.tesseract_binary', 'tesseract')),
            'pdftotext' => $this->binary((string) config('civiclens.extraction.pdftotext_binary', 'pdftotext')),
            'pdftoppm' => $this->binary((string) config('civiclens.extraction.pdftoppm_binary', 'pdftoppm')),
            'table_sidecar' => $this->tableSidecar(),
            'browser_renderer' => $this->browserRenderer(),
            'ca_bundle' => $this->caBundle(),
            'queue' => $this->queue(),
            'endpoints' => $this->endpoints(),
            'scoring' => $this->scoring(),
            'publication' => $this->publication(),
        ];

        $failing = [];

        foreach ($checks as $name => $check) {
            if (($check['ok'] ?? true) === false) {
                $failing[] = $name;
            }
        }

        return [
            'checked_at' => now()->toIso8601String(),
            'healthy' => $failing === [],
            'failing' => $failing,
            'checks' => $checks,
        ];
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    private function binary(string $path): array
    {
        if ($path === '') {
            return ['ok' => false, 'detail' => 'No path configured.'];
        }

        $resolved = str_contains($path, '/') ? $path : (trim((string) shell_exec('command -v '.escapeshellarg($path).' 2>/dev/null')) ?: '');

        return $resolved !== '' && is_executable($resolved)
            ? ['ok' => true, 'detail' => $resolved]
            : ['ok' => false, 'detail' => 'Not found or not executable: '.$path];
    }

    /**
     * @return array{ok: bool, detail: string, pages_with_tables: int}
     */
    private function tableSidecar(): array
    {
        $python = (string) config('civiclens.extraction.tables.python', '');
        $pagesWithTables = DB::table('extraction_table_cells')->distinct()->count('extraction_page_id');

        if ($python === '') {
            return [
                'ok' => false,
                'detail' => 'Not configured. Set EXTRACTION_TABLE_PYTHON.',
                'pages_with_tables' => $pagesWithTables,
            ];
        }

        // Checked before availability, not after. An environment under /tmp is a
        // fault whether or not it happens to work today — that is the whole
        // point — and testing availability first made the warning depend on
        // whether the purge had already happened.
        if (str_starts_with($python, '/tmp') || str_starts_with($python, '/private/tmp')) {
            return [
                'ok' => false,
                'detail' => 'Installed under /tmp, which the operating system purges: '.$python
                    .($this->tables->isAvailable() ? '' : ' — and the interpreter is already gone.'),
                'pages_with_tables' => $pagesWithTables,
            ];
        }

        // An interpreter that exists is not a library that imports: the
        // virtualenv survived the purge and its packages did not.
        if (! $this->tables->isAvailable()) {
            return [
                'ok' => false,
                'detail' => 'Interpreter missing: '.$python,
                'pages_with_tables' => $pagesWithTables,
            ];
        }

        return ['ok' => true, 'detail' => $python, 'pages_with_tables' => $pagesWithTables];
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    private function browserRenderer(): array
    {
        $node = (string) config('civiclens.ingestion.browser.node', '');

        if ($node === '') {
            // Not a fault. Publishers needing a browser fail closed and say so.
            return ['ok' => true, 'detail' => 'Not configured; JavaScript publishers will be refused rather than misread.'];
        }

        return is_executable($node)
            ? ['ok' => true, 'detail' => $node]
            : ['ok' => false, 'detail' => 'Configured but not executable: '.$node];
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    private function caBundle(): array
    {
        $bundle = config('civiclens.ingestion.ca_bundle');

        if (! is_string($bundle) || $bundle === '') {
            return ['ok' => true, 'detail' => 'Using the system roots.'];
        }

        return is_readable($bundle)
            ? ['ok' => true, 'detail' => $bundle]
            : ['ok' => false, 'detail' => 'Configured but unreadable, so publishers with incomplete chains will fail: '.$bundle];
    }

    /**
     * @return array{ok: bool, pending: int, failed: int, detail: string}
     */
    private function queue(): array
    {
        $failed = DB::table('failed_jobs')->count();
        $pending = DB::table('jobs')->count();

        return [
            'ok' => true,
            'pending' => $pending,
            'failed' => $failed,
            'detail' => $failed === 0 ? 'No failed jobs.' : $failed.' failed jobs recorded; inspect before assuming a source is empty.',
        ];
    }

    /**
     * @return array{ok: bool, active: int, paused: int, failing: int, detail: string}
     */
    private function endpoints(): array
    {
        $paused = SourceEndpoint::query()->whereNotNull('paused_at')->count();
        $failing = SourceEndpoint::query()->where('health_status', 'failing')->whereNull('paused_at')->count();

        return [
            'ok' => true,
            'active' => SourceEndpoint::query()->whereNull('paused_at')->count(),
            'paused' => $paused,
            'failing' => $failing,
            'detail' => $failing === 0 ? 'No active endpoint is failing.' : $failing.' active endpoints are failing.',
        ];
    }

    /**
     * @return array{ok: bool, scored: int, unscored: int, detail: string}
     */
    private function scoring(): array
    {
        $unscored = ExtractionField::query()->whereNull('gold_source')->whereNull('score_basis')->count();
        $scored = ExtractionField::query()->whereNotNull('score_basis')->count();

        return [
            'ok' => true,
            'scored' => $scored,
            'unscored' => $unscored,
            'detail' => $unscored === 0
                ? 'Every candidate carries a score.'
                : $unscored.' candidates have never been scored, so they defer by rule rather than by threshold.',
        ];
    }

    /**
     * Whether what the public site is serving still matches what the pipeline
     * has certified.
     *
     * Nothing rebuilds a snapshot automatically, which is deliberate — an
     * unattended rebuild would move published figures under a citation. The
     * cost of that choice is that a stale snapshot is a silent failure, so it
     * is reported here rather than left to be noticed.
     *
     * @return array{ok: bool, snapshot: string|null, published_figures: int, certified_figures: int, drift: int, detail: string}
     */
    private function publication(): array
    {
        $snapshot = PublicationSnapshot::current();

        // Counted the way publication counts. A value a reviewer rejected keeps
        // its accepted decision — deciding the calibration set from its own
        // calibration would be circular — and is excluded at publication, so
        // measuring drift against the raw count reported a permanent gap of
        // exactly the values the guard is there to withhold.
        $certified = ExtractionField::query()
            ->where('decision', 'accepted')
            ->where(fn ($query) => $query->whereNull('is_correct')->orWhere('is_correct', true))
            ->count();

        if (! $snapshot instanceof PublicationSnapshot) {
            return [
                // An empty corpus is not a fault. A check that fires on every
                // fresh install is a check everyone learns to ignore.
                'ok' => $certified === 0,
                'snapshot' => null,
                'published_figures' => 0,
                'certified_figures' => $certified,
                'drift' => $certified,
                'detail' => $certified === 0
                    ? 'Nothing has been certified yet, so there is nothing to publish.'
                    : $certified.' certified values exist and none are published. Run civiclens:publish.',
            ];
        }

        $published = (int) $snapshot->figure_count;
        $drift = abs($certified - $published);

        return [
            'ok' => $drift === 0,
            'snapshot' => (string) $snapshot->label,
            'published_figures' => $published,
            'certified_figures' => $certified,
            'drift' => $drift,
            'detail' => $drift === 0
                ? 'The published snapshot matches what is certified.'
                : 'The published snapshot is behind by '.$drift.' certified values. It is not wrong, only old: rebuild when you want the site to catch up.',
        ];
    }

    /**
     * Pages that could carry table structure and do not.
     */
    /**
     * What is left to detect, and what has been settled.
     *
     * Counting pages that merely lack cells conflated "there is no table here"
     * with "nobody has looked", and most pages of an audit report are prose.
     * Reported that way the figure read as outstanding work when it was mostly
     * a finished answer — the same conflation that made `skipped_pages`
     * unreadable, in the one number an operator uses to decide whether to spend
     * a night on detection.
     *
     * @return array{pages_examined: int, pages_holding_tables: int, pages_with_no_table: int, pages_not_yet_examined: int}
     */
    public function tableDetection(): array
    {
        $eligible = ExtractionPage::query()->whereNotNull('recognized_words');

        $examined = (clone $eligible)->whereNotNull('tables_detected_at')->count();
        $holding = (clone $eligible)
            ->whereIn('id', DB::table('extraction_table_cells')->select('extraction_page_id'))
            ->count();

        return [
            'pages_examined' => $examined,
            'pages_holding_tables' => $holding,
            'pages_with_no_table' => max(0, $examined - $holding),
            'pages_not_yet_examined' => (clone $eligible)->whereNull('tables_detected_at')->count(),
        ];
    }
}
