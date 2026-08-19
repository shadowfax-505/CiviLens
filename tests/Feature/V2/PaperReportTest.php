<?php

use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Services\Extraction\PaperReport;
use App\Services\Extraction\SelectiveOcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reports abstention rather than counting only what was read', function (): void {
    // A corpus reported without the pages the recognizer declined to vouch for
    // overstates how much text the system has. It is the figure a summary is
    // most tempted to omit, so it is asserted.
    ExtractionPage::factory()->count(3)->create(['extraction_path' => 'ocr_primary']);
    ExtractionPage::factory()->create(['extraction_path' => SelectiveOcrService::ABSTAINED]);

    $report = app(PaperReport::class)->build();

    expect($report['extraction']['pages'])->toBe(4)
        ->and($report['extraction']['abstained_share'])->toBe(0.25);
});

it('counts documents apart from the web pages they were found on', function (): void {
    // Discovery follows every link a listing carries, so acquisition holds each
    // publisher's own menus. Counted together, a corpus of 11 documents reported
    // 413 — the kind of figure that reaches a write-up and is never rechecked.
    $report = app(PaperReport::class)->build();

    expect($report['corpus'])->toHaveKey('documents_acquired')
        ->and($report['corpus'])->toHaveKey('web_pages_and_records_archived')
        ->and($report['corpus'])->toHaveKey('archived_by_publisher');
});

it('reports which publisher the documents actually came from', function (): void {
    // Every group-conditional claim rests on that answer, and a corpus drawn
    // from one publisher must not read as though it were drawn from several.
    $report = app(PaperReport::class)->build();

    expect($report['corpus'])->toHaveKey('documents_by_publisher')
        // Records captured from a listing are counted apart from documents:
        // they are a second publisher's data, not its files.
        ->and($report['corpus'])->toHaveKey('tender_observations');
});

it('reports the score against the one it replaced', function (): void {
    // The page-confidence baseline is recomputable from data still on the page,
    // so the comparison regenerates rather than being quoted from a note.
    $page = ExtractionPage::factory()->create(['confidence' => 90.0]);

    foreach ([[0.9, false], [0.1, true], [0.2, true]] as [$score, $correct]) {
        ExtractionField::query()->create([
            'extraction_run_id' => $page->extraction_run_id,
            'extraction_page_id' => $page->id,
            'field_key' => 'amount', 'field_type' => 'string',
            'extracted_value' => '1,25,000.50', 'script_class' => 'bn',
            'publisher_group' => 'dgcivil-bangladesh', 'confidence' => 90.0,
            'nonconformity_score' => $score, 'decision' => 'pending',
            'is_correct' => $correct, 'gold_source' => 'reviewer',
            'calibration_split' => 'calibration',
            'evidence_page_number' => 1, 'evidence_offset_start' => 0, 'evidence_offset_end' => 11,
        ]);
    }

    $score = app(PaperReport::class)->build()['score'];

    expect($score['second_read']['overall']['auc'])->toBe(1.0)
        // Every field on the page shares one page confidence, so the baseline
        // cannot separate them and lands on a coin flip.
        ->and($score['page_confidence_baseline']['auc'])->toBe(0.5)
        ->and($score['acceptance_at_alpha'])->toHaveKeys(['0.05', '0.03', '0.02']);
});

it('counts the values it cannot certify at all', function (): void {
    // A value that cannot be located is deferred and stays out of calibration.
    // The share of them is the ceiling on how much of the corpus can ever be
    // certified, which a report of accepted counts alone would hide.
    $page = ExtractionPage::factory()->create();

    ExtractionField::query()->create([
        'extraction_run_id' => $page->extraction_run_id,
        'extraction_page_id' => $page->id,
        'field_key' => 'amount', 'field_type' => 'string',
        'extracted_value' => '100.00', 'script_class' => 'bn',
        'publisher_group' => 'dgcivil-bangladesh', 'confidence' => 84.0,
        'nonconformity_score' => null, 'score_basis' => 'unlocatable',
        'decision' => 'pending',
        'evidence_page_number' => 1, 'evidence_offset_start' => 0, 'evidence_offset_end' => 6,
    ]);

    expect(app(PaperReport::class)->build()['failure_modes']['values_not_locatable'])->toBe(1);
});
