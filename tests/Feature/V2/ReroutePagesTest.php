<?php

use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Services\Extraction\PageRoutingPolicy;
use App\Services\Extraction\SelectiveOcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Vowel signs detached from any consonant, as a mis-mapped legacy font produces. */
const MOJIBAKE = 'স ূ ড ি ত ্ র ে া ি ু ';

function nativePage(string $text): ExtractionPage
{
    return ExtractionPage::factory()->create([
        'extraction_path' => PageRoutingPolicy::NATIVE,
        'extracted_text' => $text,
        'character_count' => max(51, mb_strlen($text)),
        'script_class' => 'bn',
    ]);
}

beforeEach(function (): void {
    // The re-read itself needs poppler and Tesseract. These tests are about
    // which pages are chosen, so the reader is stubbed.
    $this->mock(SelectiveOcrService::class)->shouldReceive('process')->andReturnUsing(fn ($run) => $run);
});

it('finds a page whose stored text the routing policy would now reject', function (): void {
    // A page read before the mojibake check existed kept its text layer and the
    // path `native`, and the OCR service only revisits pages already marked as
    // needing OCR. So it sits in the corpus decoding to the wrong characters.
    nativePage(str_repeat(MOJIBAKE, 40));

    $this->artisan('civiclens:reroute-pages')
        ->expectsOutputToContain('1 pages hold a text layer the routing policy would send to OCR today.')
        ->assertSuccessful();
});

it('leaves correctly encoded text alone', function (): void {
    nativePage(str_repeat('সূচিপত্র বিবরণ পৃষ্ঠা নম্বর ক্রমিক ', 40));

    $this->artisan('civiclens:reroute-pages')
        ->expectsOutputToContain('0 pages hold')
        ->assertSuccessful();
});

it('does not re-read a page that candidates already rest on', function (): void {
    // Re-reading would leave a reviewer's judgement describing characters that
    // are no longer there.
    $page = nativePage(str_repeat(MOJIBAKE, 40));

    ExtractionField::factory()->create([
        'extraction_run_id' => $page->extraction_run_id,
        'extraction_page_id' => $page->id,
    ]);

    $this->artisan('civiclens:reroute-pages')
        ->expectsOutputToContain('1 left alone because candidates already rest on their text.')
        ->assertSuccessful();
});

it('changes nothing without --apply', function (): void {
    $page = nativePage(str_repeat(MOJIBAKE, 40));

    $this->artisan('civiclens:reroute-pages')->assertSuccessful();

    expect($page->fresh()->extraction_path)->toBe(PageRoutingPolicy::NATIVE);
});

it('marks the page for re-reading with --apply', function (): void {
    $page = nativePage(str_repeat(MOJIBAKE, 40));

    $this->artisan('civiclens:reroute-pages', ['--apply' => true])->assertSuccessful();

    expect($page->fresh()->extraction_path)->toBe(PageRoutingPolicy::OCR_REQUIRED);
});
