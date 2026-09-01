<?php

use App\Models\SourceArtifactVersion;
use App\Services\Extraction\NativeExtractionService;
use App\Services\Extraction\PageRoutingPolicy;
use App\Services\Extraction\SelectiveOcrService;
use App\Services\Extraction\TesseractOcrEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

function bengaliDataMissing(): bool
{
    static $missing = null;

    if ($missing === null) {
        $process = new Process([(string) config('civiclens.extraction.ocr.tesseract_binary', 'tesseract'), '--list-langs']);
        $process->run();
        $missing = ! str_contains($process->getOutput().$process->getErrorOutput(), 'ben');
    }

    return $missing;
}

it('routes an image-only pdf to ocr and then accepts it on the primary pass', function (): void {
    config()->set('civiclens.extraction.ocr.primary_dpi', 150);
    config()->set('civiclens.extraction.ocr.accept_confidence', 80.0);

    $run = app(NativeExtractionService::class)->extract(scannedArtifact());

    expect($run->pages()->sole()->extraction_path)->toBe(PageRoutingPolicy::OCR_REQUIRED);

    $run = app(SelectiveOcrService::class)->process($run);
    $page = $run->pages()->sole();

    expect($page->extraction_path)->toBe(SelectiveOcrService::PRIMARY)
        ->and($page->confidence)->toBeGreaterThan(80.0)
        ->and($page->extracted_text)->toContain('Ministry')
        ->and($page->content_hash)->toHaveLength(64)
        ->and($page->word_count)->toBeGreaterThan(100)
        ->and($run->pages_ocr_primary)->toBe(1)
        ->and($run->pages_ocr_enhanced)->toBe(0)
        ->and($run->pages_abstained)->toBe(0);
})->skip(fn (): bool => ocrToolchainMissing(), 'poppler or tesseract is not installed');

it('permits exactly one enhanced pass and accepts when it recovers', function (): void {
    config()->set('civiclens.extraction.ocr.primary_dpi', 60);
    config()->set('civiclens.extraction.ocr.enhanced_dpi', 150);
    config()->set('civiclens.extraction.ocr.accept_confidence', 80.0);

    $run = app(SelectiveOcrService::class)->process(
        app(NativeExtractionService::class)->extract(scannedArtifact())
    );
    $page = $run->pages()->sole();

    expect($page->extraction_path)->toBe(SelectiveOcrService::ENHANCED)
        ->and($page->confidence)->toBeGreaterThan(80.0)
        ->and($page->extracted_text)->toContain('Ministry')
        ->and($run->pages_ocr_enhanced)->toBe(1)
        ->and($run->pages_ocr_primary)->toBe(0)
        ->and($run->pages_abstained)->toBe(0);
})->skip(fn (): bool => ocrToolchainMissing(), 'poppler or tesseract is not installed');

it('abstains rather than storing text it cannot vouch for', function (): void {
    config()->set('civiclens.extraction.ocr.primary_dpi', 40);
    config()->set('civiclens.extraction.ocr.enhanced_dpi', 40);
    config()->set('civiclens.extraction.ocr.accept_confidence', 80.0);

    $run = app(SelectiveOcrService::class)->process(
        app(NativeExtractionService::class)->extract(scannedArtifact())
    );
    $page = $run->pages()->sole();

    expect($page->extraction_path)->toBe(SelectiveOcrService::ABSTAINED)
        ->and($page->extracted_text)->toBeNull()
        ->and($page->content_hash)->toBeNull()
        ->and($page->character_count)->toBe(0)
        ->and($page->failure_reason)->toContain('enhanced pass')
        ->and($run->pages_abstained)->toBe(1);
})->skip(fn (): bool => ocrToolchainMissing(), 'poppler or tesseract is not installed');

it('keeps the text when the threshold is unreachable, and does not certify it', function (): void {
    // This used to abstain and store nothing, so recovering the page meant
    // recognising it again — and 1,386 pages were in that state. The mean is a
    // page-level statistic while the guarantee is per field: on one measured
    // page the mean was 74.1 while its 56 numeric tokens averaged 83.5, so
    // discarding the page threw away fifty-six figures that were read well.
    //
    // Kept, marked, and not certified. Whether any individual value is good
    // enough to offer a reviewer is decided per token.
    config()->set('civiclens.extraction.ocr.primary_dpi', 150);
    config()->set('civiclens.extraction.ocr.enhanced_dpi', 300);
    config()->set('civiclens.extraction.ocr.accept_confidence', 99.9);

    $run = app(SelectiveOcrService::class)->process(
        app(NativeExtractionService::class)->extract(scannedArtifact())
    );
    $page = $run->pages()->sole();

    expect($page->extraction_path)->toBe(SelectiveOcrService::LOW_CONFIDENCE)
        ->and($page->extraction_path)->not->toBe(SelectiveOcrService::ABSTAINED)
        ->and($page->extracted_text)->not->toBeNull()
        ->and($page->confidence)->toBeGreaterThan(80.0)
        ->and($page->confidence)->toBeLessThan(99.9)
        // The reason it fell short travels with it, so a later reader knows
        // this is a page the recognizer declined rather than one nobody tried.
        ->and($page->failure_reason)->toContain('below the acceptance threshold');
})->skip(fn (): bool => ocrToolchainMissing(), 'poppler or tesseract is not installed');

it('leaves a fully native run untouched', function (): void {
    Storage::fake('local');
    $contents = (string) file_get_contents(__DIR__.'/../../Fixtures/Extraction/native-text.pdf');
    Storage::disk('local')->put('ingestion/approved/demo/native-text.pdf', $contents);
    $artifact = SourceArtifactVersion::factory()->create([
        'storage_disk' => 'local',
        'storage_path' => 'ingestion/approved/demo/native-text.pdf',
        'media_type' => 'application/pdf',
        'byte_size' => strlen($contents),
        'is_quarantined' => false,
        'malware_status' => 'clean',
    ]);

    $run = app(NativeExtractionService::class)->extract($artifact);
    $before = $run->pages()->sole()->only(['extraction_path', 'extracted_text']);

    $run = app(SelectiveOcrService::class)->process($run);

    expect($run->pages()->sole()->only(['extraction_path', 'extracted_text']))->toBe($before)
        ->and($run->pages_ocr_primary)->toBe(0)
        ->and($run->pages_abstained)->toBe(0);
})->skip(fn (): bool => ocrToolchainMissing(), 'poppler or tesseract is not installed');

it('reports no confidence when the engine recognized nothing', function (): void {
    $blank = tempnam(sys_get_temp_dir(), 'civiclens-blank-').'.png';
    $width = $height = 64;
    $raw = str_repeat("\x00".str_repeat("\xff", $width), $height);
    $chunk = function (string $type, string $data): string {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    };
    file_put_contents($blank, "\x89PNG\r\n\x1a\n"
        .$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 0, 0, 0, 0))
        .$chunk('IDAT', gzcompress($raw))
        .$chunk('IEND', ''));

    $result = app(TesseractOcrEngine::class)->recognize($blank, 150, 3);
    @unlink($blank);

    expect($result->meanConfidence)->toBeNull()
        ->and($result->wordCount)->toBe(0)
        ->and($result->isConfident(0.0))->toBeFalse()
        ->and($result->engine)->toBe('tesseract')
        ->and($result->engineVersion)->not->toBe('unknown');
})->skip(fn (): bool => ocrToolchainMissing(), 'poppler or tesseract is not installed');

it('has bengali language data available for mixed-script documents', function (): void {
    expect(bengaliDataMissing())->toBeFalse();
})->skip(fn (): bool => ocrToolchainMissing(), 'poppler or tesseract is not installed');
