<?php

use App\Data\Extraction\ExtractedPage;
use App\Exceptions\Extraction\ExtractionFailed;
use App\Models\SourceArtifactVersion;
use App\Services\Extraction\ExtractionRoutingReport;
use App\Services\Extraction\NativeExtractionService;
use App\Services\Extraction\PageRoutingPolicy;
use App\Services\Extraction\PdfNativeTextExtractor;
use App\Services\Extraction\ScriptClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function extractionFixture(string $name): string
{
    return __DIR__.'/../../Fixtures/Extraction/'.$name;
}

function storedArtifact(string $fixture, string $mediaType = 'application/pdf'): SourceArtifactVersion
{
    Storage::fake('local');
    $contents = (string) file_get_contents(extractionFixture($fixture));
    Storage::disk('local')->put('ingestion/approved/demo/'.$fixture, $contents);

    return SourceArtifactVersion::factory()->create([
        'storage_disk' => 'local',
        'storage_path' => 'ingestion/approved/demo/'.$fixture,
        'media_type' => $mediaType,
        'byte_size' => strlen($contents),
        'is_quarantined' => false,
        'malware_status' => 'clean',
    ]);
}

it('classifies bengali, latin, mixed, and scriptless text', function (): void {
    $classifier = new ScriptClassifier;

    expect($classifier->classify('বাংলাদেশ সরকার অর্থ মন্ত্রণালয়'))->toBe('bn')
        ->and($classifier->classify('Ministry of Finance budget allocation'))->toBe('en')
        ->and($classifier->classify('অর্থ মন্ত্রণালয় Budget Allocation Report 2026'))->toBe('mixed')
        ->and($classifier->classify('12345 —— 67.89'))->toBe('unknown');
});

it('measures text density per square inch rather than per page', function (): void {
    $a4 = new ExtractedPage(1, str_repeat('a', 967), 595.276, 841.89);
    $a5 = new ExtractedPage(1, str_repeat('a', 967), 419.528, 595.276);

    expect($a4->density())->toBeGreaterThan(9.0)->toBeLessThan(11.0)
        ->and($a5->density())->toBeGreaterThan($a4->density() * 1.9)
        ->and((new ExtractedPage(1, '', 595.276, 841.89))->density())->toBe(0.0);
});

it('reads the text layer and page geometry from a born-digital pdf', function (): void {
    $result = app(PdfNativeTextExtractor::class)->extract(extractionFixture('native-text.pdf'));

    expect($result->engine)->toBe('poppler')
        ->and($result->engineVersion)->not->toBe('unknown')
        ->and($result->pageCount())->toBe(1)
        ->and($result->pages[0]->text)->toContain('Ministry of Finance')
        ->and($result->pages[0]->widthPoints)->toBe(595.276)
        ->and($result->pages[0]->characterCount())->toBeGreaterThan(2000);
});

it('routes a dense text layer to native and an absent one to ocr', function (): void {
    $policy = new PageRoutingPolicy;
    $dense = new ExtractedPage(1, str_repeat('a ', 1500), 595.276, 841.89);
    $empty = new ExtractedPage(1, '', 595.276, 841.89);

    expect($policy->decide($dense))->toBe(PageRoutingPolicy::NATIVE)
        ->and($policy->decide($empty))->toBe(PageRoutingPolicy::OCR_REQUIRED)
        ->and($policy->summarize([PageRoutingPolicy::NATIVE, PageRoutingPolicy::NATIVE]))->toBe(PageRoutingPolicy::NATIVE)
        ->and($policy->summarize([PageRoutingPolicy::OCR_REQUIRED]))->toBe(PageRoutingPolicy::OCR_REQUIRED)
        ->and($policy->summarize([PageRoutingPolicy::NATIVE, PageRoutingPolicy::OCR_REQUIRED]))->toBe('mixed')
        ->and($policy->summarize([]))->toBe('empty');
});

it('honours a reconfigured density threshold as an operating point', function (): void {
    $policy = new PageRoutingPolicy;
    $sparse = new ExtractedPage(1, str_repeat('a ', 100), 595.276, 841.89);

    config()->set('civiclens.extraction.native_density_threshold', 1.5);
    expect($policy->decide($sparse))->toBe(PageRoutingPolicy::NATIVE);

    config()->set('civiclens.extraction.native_density_threshold', 50.0);
    expect($policy->decide($sparse))->toBe(PageRoutingPolicy::OCR_REQUIRED);
});

it('records a completed native run with page level measurements', function (): void {
    $artifact = storedArtifact('native-text.pdf');

    $run = app(NativeExtractionService::class)->extract($artifact);

    expect($run->status)->toBe('completed')
        ->and($run->routing_decision)->toBe(PageRoutingPolicy::NATIVE)
        ->and($run->engine)->toBe('poppler')
        ->and($run->page_count)->toBe(1)
        ->and($run->pages_native)->toBe(1)
        ->and($run->config_hash)->toHaveLength(64)
        ->and($run->peak_memory_bytes)->toBeGreaterThan(0);

    $page = $run->pages()->sole();

    expect($page->extraction_path)->toBe(PageRoutingPolicy::NATIVE)
        ->and($page->script_class)->toBe('en')
        ->and($page->confidence)->toBe(1.0)
        ->and($page->extracted_text)->toContain('Ministry of Finance')
        ->and($page->content_hash)->toHaveLength(64)
        ->and($page->text_layer_density)->toBeGreaterThan(1.5);
});

it('routes a pdf without a text layer to ocr and stores no text for it', function (): void {
    $artifact = storedArtifact('no-text-layer.pdf');

    $run = app(NativeExtractionService::class)->extract($artifact);
    $page = $run->pages()->sole();

    expect($run->status)->toBe('completed')
        ->and($run->routing_decision)->toBe(PageRoutingPolicy::OCR_REQUIRED)
        ->and($run->pages_native)->toBe(0)
        ->and($page->extraction_path)->toBe(PageRoutingPolicy::OCR_REQUIRED)
        ->and($page->character_count)->toBe(0)
        ->and($page->text_layer_density)->toBe(0.0)
        ->and($page->extracted_text)->toBeNull()
        ->and($page->confidence)->toBeNull();
});

it('refuses to extract a quarantined artifact', function (): void {
    $artifact = storedArtifact('native-text.pdf');
    $artifact->forceFill(['is_quarantined' => true])->saveQuietly();

    expect(fn () => app(NativeExtractionService::class)->extract($artifact))
        ->toThrow(ExtractionFailed::class, 'Quarantined');
});

it('marks the run failed and hides storage detail when the artifact is missing', function (): void {
    $artifact = storedArtifact('native-text.pdf');
    Storage::disk('local')->delete($artifact->storage_path);

    expect(fn () => app(NativeExtractionService::class)->extract($artifact))
        ->toThrow(ExtractionFailed::class);

    $run = $artifact->refresh()->extractionRuns()->sole();

    expect($run->status)->toBe('failed')
        ->and($run->failure_reason)->not->toContain('ingestion/approved')
        ->and($run->completed_at)->not->toBeNull();
});

it('reports the born-digital share and its per-script breakdown', function (): void {
    app(NativeExtractionService::class)->extract(storedArtifact('native-text.pdf'));
    app(NativeExtractionService::class)->extract(storedArtifact('no-text-layer.pdf'));

    $summary = app(ExtractionRoutingReport::class)->build();

    expect($summary['total_pages'])->toBe(2)
        ->and($summary['native_pages'])->toBe(1)
        ->and($summary['ocr_required_pages'])->toBe(1)
        ->and($summary['born_digital_share'])->toBe(0.5)
        ->and($summary['failed_runs'])->toBe(0)
        ->and($summary['threshold_chars_per_square_inch'])->toBe(1.5)
        ->and($summary['native_share_by_script']['en'])->toBe(1.0)
        ->and($summary['native_share_by_script']['unknown'])->toBe(0.0);
});

it('reports nothing rather than a misleading zero on an empty corpus', function (): void {
    expect(app(ExtractionRoutingReport::class)->build()['born_digital_share'])->toBeNull();
});

it('extracts plain text sources without shelling out', function (): void {
    $artifact = storedArtifact('native-text.pdf', 'text/plain');

    $run = app(NativeExtractionService::class)->extract($artifact);

    expect($run->engine)->toBe('native-text')
        ->and($run->page_count)->toBe(1);
});
