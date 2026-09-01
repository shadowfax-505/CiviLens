<?php

use App\Services\Extraction\NativeExtractionService;
use App\Services\Extraction\SelectiveOcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('recognises up to the ceiling and records what it left', function (): void {
    // OCR runs about 65 seconds a page, so a 672-page scan is twelve hours of
    // the only worker for one document. Refusing it on page count stored
    // nothing at all; reading the first part and saying so is strictly better,
    // and the abstention carries the reason.
    //
    // A ceiling of zero exercises the mechanism without paying for
    // recognition: every page is beyond it.
    config()->set('civiclens.extraction.max_ocr_pages', 0);

    $run = app(NativeExtractionService::class)->extract(scannedArtifact());
    $run = app(SelectiveOcrService::class)->process($run);

    $page = $run->pages()->sole();

    expect($run->fresh()->status)->not->toBe('failed')
        ->and($page->extraction_path)->toBe(SelectiveOcrService::ABSTAINED)
        ->and($page->failure_reason)->toContain('OCR of one document has a ceiling');
})->skip(fn (): bool => ocrToolchainMissing(), 'poppler or tesseract is not installed');

it('reads a long document that carries a text layer', function (): void {
    // A 587-page audit report with a text layer costs seconds to read, and the
    // old limit of 500 refused it outright while letting through a 400-page
    // scan that costs seven hours. Page count was never the expense.
    expect((int) config('civiclens.extraction.max_pages'))->toBeGreaterThan(587);
});

it('bounds recognition rather than reading', function (): void {
    // The two limits measure different things and only one of them is a cost.
    expect((int) config('civiclens.extraction.max_ocr_pages'))
        ->toBeLessThan((int) config('civiclens.extraction.max_pages'));
});
