<?php

use App\Data\Extraction\ExtractedPage;

it('counts words on a page whose text is not valid utf-8', function (): void {
    // Tesseract emits a few invalid bytes on Bengali pages. One is enough to
    // make every preg_* call with /u return false, and the page then reported
    // zero words while the engine had recognised 222. A count of zero on a full
    // page is worse than an approximate one, because it looks measured.
    $text = "\xE0\xA6\xAC\xE0\xA6\xBF \xFF\xFE invalid bytes \xE0\xA6\xAC here";

    expect(mb_check_encoding($text, 'UTF-8'))->toBeFalse();

    $page = new ExtractedPage(1, $text, 595.276, 841.89);

    expect($page->wordCount())->toBeGreaterThan(0);
});

it('still counts words normally on valid text', function (): void {
    $page = new ExtractedPage(1, 'Ministry of Education budget line', 595.276, 841.89);

    expect($page->wordCount())->toBe(5);
});
