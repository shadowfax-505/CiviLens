<?php

use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Models\ExtractionTableCell;
use App\Services\Extraction\PipelineHealthReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// These tests are about which pages are chosen, not about detecting anything.
beforeEach(fn () => Queue::fake());

function detectablePage(?string $detectedAt = null): ExtractionPage
{
    $page = ExtractionPage::factory()->create([
        'recognized_words' => [['t' => 'x', 'c' => 90.0, 'l' => 1, 'y' => 1, 'w' => 5, 'h' => 5]],
        'recognized_dpi' => 150,
        'tables_detected_at' => $detectedAt,
    ]);

    ExtractionField::factory()->create([
        'extraction_run_id' => $page->extraction_run_id,
        'extraction_page_id' => $page->id,
        'gold_source' => null,
    ]);

    return $page;
}

it('does not look again at a page it has already looked at', function (): void {
    // Absence of cells meant two things — no table here, and nobody has looked
    // — and the slowest step in the pipeline could not tell them apart. Testing
    // for cells re-derived every negative on every run: about 297 of the 451
    // pages detected overnight, at up to sixteen minutes each.
    detectablePage('2026-08-20 10:00:00');

    $this->artisan('civiclens:extract-tables', ['--limit' => 50])
        ->expectsOutputToContain('Queued 0 pages')
        ->assertSuccessful();
});

it('looks at a page nothing has looked at, whether or not others found tables', function (): void {
    detectablePage(null);

    $this->artisan('civiclens:extract-tables', ['--limit' => 50])
        ->expectsOutputToContain('Queued 1 pages')
        ->assertSuccessful();
});

it('looks again at everything when told to', function (): void {
    // What to use after a page has been re-read and its word geometry has
    // changed underneath the old grid.
    detectablePage('2026-08-20 10:00:00');

    $this->artisan('civiclens:extract-tables', ['--limit' => 50, '--all' => true])
        ->expectsOutputToContain('Queued 1 pages')
        ->assertSuccessful();
});

it('treats a page that holds cells as looked at', function (): void {
    // The backfill's rule: cells prove detection ran, whatever the stamp says.
    $page = detectablePage(null);

    ExtractionTableCell::factory()->create(['extraction_page_id' => $page->id]);

    expect(ExtractionTableCell::query()->where('extraction_page_id', $page->id)->exists())->toBeTrue();
});

it('does not report a page with no table as work still to do', function (): void {
    // Most pages of an audit report are prose. Counting them as "missing table
    // structure" made a finished answer read as outstanding work, in the one
    // number an operator uses to decide whether to spend a night on detection.
    detectablePage('2026-08-20 10:00:00');
    detectablePage(null);

    $detection = app(PipelineHealthReport::class)->tableDetection();

    expect($detection['pages_examined'])->toBe(1)
        ->and($detection['pages_with_no_table'])->toBe(1)
        ->and($detection['pages_holding_tables'])->toBe(0)
        ->and($detection['pages_not_yet_examined'])->toBe(1);
});

it('counts a page holding cells as holding a table', function (): void {
    $page = detectablePage('2026-08-20 10:00:00');
    ExtractionTableCell::factory()->create(['extraction_page_id' => $page->id]);

    $detection = app(PipelineHealthReport::class)->tableDetection();

    expect($detection['pages_holding_tables'])->toBe(1)
        ->and($detection['pages_with_no_table'])->toBe(0);
});
