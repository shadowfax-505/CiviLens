<?php

use App\Jobs\ExtractPageTables;
use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Models\ExtractionTableCell;
use App\Models\Role;
use App\Models\User;
use App\Services\Extraction\ReviewCandidateGenerator;
use App\Services\Extraction\TableStructureDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function tableAdmin(): User
{
    $role = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
    $admin = User::factory()->create();
    $admin->roles()->attach($role);

    return $admin;
}

/** A row of a financial table: a line item label and three yearly figures. */
function tableRow(ExtractionPage $page): ExtractionTableCell
{
    ExtractionTableCell::factory()->create([
        'extraction_page_id' => $page->id, 'row_index' => 0, 'column_index' => 0,
        'text' => 'প্রবৃদ্ধি (%)', 'word_count' => 2,
    ]);

    $value = ExtractionTableCell::factory()->create([
        'extraction_page_id' => $page->id, 'row_index' => 0, 'column_index' => 1,
        'text' => '১৫,৬২৯', 'word_count' => 1,
    ]);

    ExtractionTableCell::factory()->create([
        'extraction_page_id' => $page->id, 'row_index' => 0, 'column_index' => 2,
        'text' => '১৬,০৩১', 'word_count' => 1,
    ]);

    return $value;
}

it('reports table detection as unavailable rather than failing without the sidecar', function (): void {
    // A machine without a gigabyte of models installed must keep working. The
    // pages simply carry no table structure.
    config(['civiclens.extraction.tables.python' => '']);

    expect(app(TableStructureDetector::class)->isAvailable())->toBeFalse();
});

it('gives a cell-derived candidate the row and column it sat in', function (): void {
    // Generated from the cell rather than matched to it afterwards: 81 of 300
    // sampled values appear more than once on their own page, so choosing a cell
    // for a repeated figure would invent the provenance it claims to record.
    $page = ExtractionPage::factory()->create(['script_class' => 'bn', 'confidence' => 84.0]);
    $value = tableRow($page);

    $summary = app(ReviewCandidateGenerator::class)->generateFromCells(50);

    expect($summary['created'])->toBeGreaterThan(0);

    $field = ExtractionField::query()->where('extraction_table_cell_id', $value->id)->first();

    expect($field)->not->toBeNull()
        ->and($field->extracted_value)->toBe('১৫,৬২৯')
        ->and($field->tableCell->row_index)->toBe(0)
        ->and($field->tableCell->column_index)->toBe(1);
});

it('shows the row and its label beside the value', function (): void {
    $page = ExtractionPage::factory()->create(['script_class' => 'bn', 'confidence' => 84.0]);
    $value = tableRow($page);
    app(ReviewCandidateGenerator::class)->generateFromCells(50);

    $this->actingAs(tableAdmin())->get('/admin/sources/review')
        ->assertOk()
        ->assertSee('Its row on the page')
        ->assertSee('প্রবৃদ্ধি (%)')
        // The neighbouring column is visible, which is what lets a reviewer see
        // the figure among its siblings.
        ->assertSee('১৬,০৩১')
        // Not a fixed column: the queue draws at random, so either figure in the
        // row may be the one under review.
        ->assertSee('table row 1, column');
});

it('says the row is shown as read rather than as verified', function (): void {
    // A mis-split row would attach a confident label to the wrong figure, which
    // is worse than no label at all.
    $page = ExtractionPage::factory()->create(['script_class' => 'bn', 'confidence' => 84.0]);
    tableRow($page);
    app(ReviewCandidateGenerator::class)->generateFromCells(50);

    $this->actingAs(tableAdmin())->get('/admin/sources/review')
        ->assertOk()
        ->assertSee('shown as read, not as verified')
        ->assertSee('play no part in the judgement');
});

it('still works for a value that came from flat page text', function (): void {
    // Pages without a table carry no row, and the screen must not require one.
    $page = ExtractionPage::factory()->create(['extracted_text' => 'ক্রমিক 1,25,000.50 তারিখ']);
    ExtractionField::query()->create([
        'extraction_run_id' => $page->extraction_run_id,
        'extraction_page_id' => $page->id,
        'field_key' => 'amount', 'field_type' => 'string',
        'extracted_value' => '1,25,000.50', 'script_class' => 'bn',
        'publisher_group' => 'dgcivil-bangladesh', 'confidence' => 84.0,
        'nonconformity_score' => 0.16, 'decision' => 'pending',
        'evidence_page_number' => 1, 'evidence_offset_start' => 7, 'evidence_offset_end' => 18,
    ]);

    $this->actingAs(tableAdmin())->get('/admin/sources/review')
        ->assertOk()
        ->assertSee('Do these characters match the page?')
        ->assertDontSee('Its row on the page');
});

it('refuses to take a value from a cell that is really a line of prose', function (): void {
    // Measured on page 34 of the 2017-18 audit report: the detector's box began
    // one row below the heading and ran one row past the table, sweeping the
    // paragraph underneath into five cells of three to seven words each. A date
    // taken from that paragraph would be filed under a row and column it was
    // never in.
    $page = ExtractionPage::factory()->create(['script_class' => 'bn', 'confidence' => 84.0]);

    ExtractionTableCell::factory()->create([
        'extraction_page_id' => $page->id, 'row_index' => 2, 'column_index' => 1,
        'text' => 'উল্লেখ্য ২০১৭-২০১৮ অর্থবছর হতে পেনশন ও আনুতোষিকের ১২,৩৪,৫৬৭ বাজেট',
        'word_count' => 7,
    ]);

    $value = ExtractionTableCell::factory()->create([
        'extraction_page_id' => $page->id, 'row_index' => 0, 'column_index' => 1,
        'text' => '১৫,৬২৯', 'word_count' => 1,
    ]);

    app(ReviewCandidateGenerator::class)->generateFromCells(50);

    $fields = ExtractionField::query()->whereNotNull('extraction_table_cell_id')->get();

    expect($fields)->toHaveCount(1)
        ->and($fields->first()->extraction_table_cell_id)->toBe($value->id);
});

it('gives the sidecar less time than the job that waits for it', function (): void {
    // A sidecar allowed to outlive its job is killed mid-page and the work is
    // lost; a sidecar cut off early burns the same CPU and then fails. The 300s
    // ceiling this replaced turned CPU contention into failed jobs that had
    // already spent five minutes to fail.
    $sidecar = (int) config('civiclens.extraction.tables.timeout_seconds');
    $job = (new ExtractPageTables(1))->timeout;

    expect($sidecar)->toBeLessThan($job)
        // And enough room for a page that takes far longer than the 90s an idle
        // machine needs.
        ->and($sidecar)->toBeGreaterThan(600);
});
