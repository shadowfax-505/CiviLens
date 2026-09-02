<?php

use App\Services\Extraction\NativeWordGeometry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('takes word positions from a text layer the document already declares', function (): void {
    // A value is located by matching words against boxes, and boxes came only
    // from OCR — so a page read from its own text layer had text and no
    // geometry, and every value on it was unlocatable and deferred by rule:
    // 78,551 of 79,226 court candidates.
    if (ocrToolchainMissing()) {
        $this->markTestSkipped('poppler is not installed on this machine.');
    }

    $boxes = app(NativeWordGeometry::class)
        ->forPage(base_path('tests/Fixtures/Extraction/native-text.pdf'), 1, 150);

    expect($boxes)->not->toBeEmpty();

    $word = $boxes[0];

    expect($word)->toHaveKeys(['t', 'c', 'l', 'y', 'w', 'h'])
        ->and(trim($word['t']))->not->toBe('')
        ->and($word['w'])->toBeGreaterThan(0)
        ->and($word['h'])->toBeGreaterThan(0);
});

it('scales points to pixels at the resolution it is given', function (): void {
    // PDF user space is 72 units to the inch and stored boxes are pixels, so
    // the same page at 300 must land twice as far across as at 150. Getting
    // this wrong puts every crop in the wrong place, which is the failure that
    // produced one-pixel crops when DPI and geometry disagreed.
    if (ocrToolchainMissing()) {
        $this->markTestSkipped('poppler is not installed on this machine.');
    }

    $geometry = app(NativeWordGeometry::class);
    $path = base_path('tests/Fixtures/Extraction/native-text.pdf');

    $low = $geometry->forPage($path, 1, 150);
    $high = $geometry->forPage($path, 1, 300);

    expect($low)->not->toBeEmpty()->and($high)->toHaveCount(count($low));

    // Allow a pixel of rounding on each side.
    expect(abs($high[0]['l'] - ($low[0]['l'] * 2)))->toBeLessThanOrEqual(2)
        ->and(abs($high[0]['w'] - ($low[0]['w'] * 2)))->toBeLessThanOrEqual(2);
});

it('records a declared position as certain rather than as a guess', function (): void {
    // The document states where its words are; nothing recognised them. Given
    // a recognition confidence, the token gate would treat a declared position
    // as a poor read.
    if (ocrToolchainMissing()) {
        $this->markTestSkipped('poppler is not installed on this machine.');
    }

    $boxes = app(NativeWordGeometry::class)
        ->forPage(base_path('tests/Fixtures/Extraction/native-text.pdf'), 1, 150);

    expect(collect($boxes)->pluck('c')->unique()->all())->toBe([100.0]);
});
