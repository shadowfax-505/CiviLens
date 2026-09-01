<?php

use App\Models\SourceArtifactVersion;
use App\Services\Extraction\HtmlNativeExtractor;
use App\Services\Extraction\NativeExtractionService;
use App\Services\Extraction\SelectiveOcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function utf16Page(string $markup, string $encoding = 'UTF-16BE'): string
{
    $path = sys_get_temp_dir().'/civiclens-utf16-'.bin2hex(random_bytes(6)).'.html';
    $bom = $encoding === 'UTF-16BE' ? "\xFE\xFF" : "\xFF\xFE";

    file_put_contents($path, $bom.mb_convert_encoding($markup, $encoding, 'UTF-8'));

    return $path;
}

it('reads a page served as UTF-16', function (): void {
    // bdlaws.minlaw.gov.bd, holding the Constitution and every Act, serves
    // UTF-16. DOMDocument reads bytes as single-byte text, meets the
    // byte-order mark and stops: a 2.5 MB page of statute yielded two
    // characters, which is the BOM rendered as Latin-1.
    $path = utf16Page('<html><body><p>THE WILLS AND INTESTACY REGULATION, 1799</p></body></html>');

    $result = app(HtmlNativeExtractor::class)->extract($path);

    @unlink($path);

    expect($result->pages[0]->text)->toContain('WILLS AND INTESTACY REGULATION')
        // The mark survives conversion as U+FEFF and would otherwise open every
        // extracted page.
        ->and($result->pages[0]->text)->not->toStartWith("\u{FEFF}");
});

it('reads a little-endian page too', function (): void {
    $path = utf16Page('<html><body><p>Index of Laws of Bangladesh</p></body></html>', 'UTF-16LE');

    $result = app(HtmlNativeExtractor::class)->extract($path);

    @unlink($path);

    expect($result->pages[0]->text)->toContain('Index of Laws of Bangladesh');
});

it('leaves an ordinary UTF-8 page alone', function (): void {
    $path = sys_get_temp_dir().'/civiclens-utf8-'.bin2hex(random_bytes(6)).'.html';
    file_put_contents($path, '<html><body><p>বাংলাদেশের আইন</p></body></html>');

    $result = app(HtmlNativeExtractor::class)->extract($path);

    @unlink($path);

    expect($result->pages[0]->text)->toContain('বাংলাদেশের আইন');
});

it('abstains instead of failing the whole run when a format has no page image', function (): void {
    // A thin text layer routes a page to OCR, and OCR rasterises with pdftoppm,
    // which only reads PDFs. An HTML page with little text was handed to a
    // rasterizer that cannot read it, the exception failed the run, and every
    // other page in that run went with it — 200 runs sat failed that way.
    Storage::fake('local');
    Storage::disk('local')->put('ingestion/approved/demo/stub.html', '<html><body><p>.</p></body></html>');

    $artifact = SourceArtifactVersion::factory()->create([
        'storage_disk' => 'local',
        'storage_path' => 'ingestion/approved/demo/stub.html',
        'media_type' => 'text/html',
        'byte_size' => 45,
        'is_quarantined' => false,
        'malware_status' => 'clean',
    ]);

    $run = app(NativeExtractionService::class)->extract($artifact);
    $run = app(SelectiveOcrService::class)->process($run);

    $page = $run->pages()->sole();

    expect($run->fresh()->status)->not->toBe('failed')
        ->and($page->extraction_path)->toBe(SelectiveOcrService::ABSTAINED)
        ->and($page->failure_reason)->toContain('No page image exists for this format');
});
