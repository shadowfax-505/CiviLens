<?php

use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Services\Extraction\ValueLocator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Word boxes laid out left to right, as a text layer reports them. */
function wordsAcross(array $words): array
{
    $boxes = [];
    $left = 10;

    foreach ($words as $word) {
        $width = max(8, mb_strlen($word) * 9);
        $boxes[] = ['t' => $word, 'c' => 100.0, 'l' => $left, 'y' => 40, 'w' => $width, 'h' => 20];
        $left += $width + 6;
    }

    return $boxes;
}

function locateOn(string $value, array $words): array
{
    ['page' => $page] = publishableDocument();
    $page->forceFill(['recognized_words' => wordsAcross($words), 'recognized_dpi' => 150])->save();

    $field = ExtractionField::factory()->create([
        'extraction_run_id' => $page->extraction_run_id,
        'extraction_page_id' => $page->id,
        'extracted_value' => $value,
    ]);

    return app(ValueLocator::class)->locate($field, ExtractionPage::query()->findOrFail($page->id));
}

it('finds a case number, which runs to six words', function (): void {
    // Four words was right while every value was a figure — an amount or a date
    // is one word. A case number is six, so it located 0% of the time, not for
    // want of geometry but because the search could never span it.
    $boxes = locateOn('Writ Petition No. 8951 of 2025', [
        'IN', 'THE', 'MATTER', 'OF', 'Writ', 'Petition', 'No.', '8951', 'of', '2025', 'filed',
    ]);

    expect($boxes)->not->toBeEmpty();

    // The box covers the whole citation, not one word of it.
    expect($boxes[0]['right'] - $boxes[0]['left'])->toBeGreaterThan(150);
});

it('finds a statutory reference, which runs to eight', function (): void {
    expect(locateOn('Section 410 of the Code of Criminal Procedure', [
        'under', 'Section', '410', 'of', 'the', 'Code', 'of', 'Criminal', 'Procedure', 'was', 'filed',
    ]))->not->toBeEmpty();
});

it('still finds a figure, which is one word', function (): void {
    // The common case must not regress: the inner loop stops as soon as the
    // joined run is longer than the value, so a short value still examines a
    // short span however high the limit goes.
    expect(locateOn('৩,৯৮,৭৪৮', ['মোট', '৩,৯৮,৭৪৮', 'টাকা', 'ব্যয়']))->not->toBeEmpty();
});

it('does not match a run of words that is not the value', function (): void {
    // A longer span must not become a looser one. The run has to equal the
    // value, so joining more words can only ever match a longer value.
    expect(locateOn('Writ Petition No. 8951 of 2025', [
        'Criminal', 'Appeal', 'No.', '7802', 'of', '2025', 'was', 'heard',
    ]))->toBeEmpty();
});
