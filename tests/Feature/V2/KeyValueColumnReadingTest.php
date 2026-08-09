<?php

use App\Data\Extraction\RecognizedWord;
use App\Services\Extraction\KeyValueExtractor;

/**
 * Geometry taken from a real e-GP tender notice, page 1.
 *
 * Two label/value pairs share a line. The label column starts at x=40, the
 * value column at x=154, and the second pair's label starts at x=306. The
 * distances are what make this layout hard: the gutter from a label to its
 * value is 67px against an 11px label height, while the next field's label sits
 * only 55px past the end of the value.
 *
 * @return list<RecognizedWord>
 */
function egpNoticeLine(): array
{
    return [
        new RecognizedWord('Ministry', 100.0, 40, 67, 41, 11),
        new RecognizedWord(':', 100.0, 84, 67, 3, 11),
        new RecognizedWord('Ministry', 100.0, 154, 68, 36, 10),
        new RecognizedWord('of', 100.0, 193, 68, 9, 10),
        new RecognizedWord('Education', 100.0, 205, 68, 46, 10),
        new RecognizedWord('Division', 100.0, 306, 67, 40, 11),
        new RecognizedWord(':', 100.0, 349, 67, 4, 11),
    ];
}

it('crosses the gutter to reach the value in the next column', function (): void {
    // Before this, the label's own colon was taken as the value and the read
    // stopped there, so every field on a two-column form reported ":".
    $read = app(KeyValueExtractor::class)->extract('Ministry', egpNoticeLine());

    expect($read)->not->toBeNull()
        ->and($read['value'])->toBe('Ministry of Education');
});

it('stops before the next field rather than reading on at column width', function (): void {
    // The next label is closer to the end of the value than the value was to
    // its own label, so one threshold cannot separate them. Reading resumes at
    // word spacing once the gutter has been crossed.
    $read = app(KeyValueExtractor::class)->extract('Ministry', egpNoticeLine());

    expect($read['value'])->not->toContain('Division')
        ->and($read['value'])->not->toContain(':');
});

it('does not treat the label terminator as content', function (): void {
    $read = app(KeyValueExtractor::class)->extract('Ministry', egpNoticeLine());

    expect($read['confidences'])->toHaveCount(3);
});

it('reads a value that sits immediately after its label', function (): void {
    // Dense layouts put the value a word space away. The wider gutter allowance
    // must not change what happens when there is no gutter at all.
    $words = [
        new RecognizedWord('Nature', 100.0, 108, 173, 34, 10),
        new RecognizedWord(':', 100.0, 145, 173, 3, 10),
        new RecognizedWord('Works', 100.0, 154, 173, 30, 10),
        new RecognizedWord('Procurement', 100.0, 306, 173, 65, 10),
    ];

    expect(app(KeyValueExtractor::class)->extract('Nature', $words)['value'])->toBe('Works');
});

it('refuses a value separated by more than the configured gutter', function (): void {
    // The allowance is an operating point, not a licence to read anything on
    // the line. A value far past it belongs to a different column.
    config(['civiclens.extraction.kv_gap_multiple' => 2]);

    $read = app(KeyValueExtractor::class)->extract('Ministry', egpNoticeLine());

    expect($read)->toBeNull();
});

it('stops at the next label even when no gutter separates it', function (): void {
    // Geometry from the same notice. The value "Open Tendering Method" ends at
    // x=277 and the next label "Budget Type :" starts at x=289 — a 12px gap
    // against a 10px line height, which is ordinary word spacing. No distance
    // threshold separates that from the space between two words of one value.
    $words = [
        new RecognizedWord('Procurement', 100.0, 40, 280, 65, 10),
        new RecognizedWord('Method', 100.0, 108, 280, 38, 10),
        new RecognizedWord(':', 100.0, 149, 280, 3, 10),
        new RecognizedWord('Open', 100.0, 165, 280, 26, 10),
        new RecognizedWord('Tendering', 100.0, 194, 280, 45, 10),
        new RecognizedWord('Method', 100.0, 242, 280, 35, 10),
        new RecognizedWord('Budget', 100.0, 289, 280, 36, 10),
        new RecognizedWord('Type', 100.0, 328, 280, 25, 10),
        new RecognizedWord(':', 100.0, 355, 280, 4, 10),
    ];

    expect(app(KeyValueExtractor::class)->extract('Procurement Method', $words)['value'])
        ->toBe('Open Tendering Method');
});

it('does not mistake a value containing a colon for a label', function (): void {
    // A label ends in a colon. A time does not, and treating every colon as a
    // label boundary would drop the value entirely.
    $words = [
        new RecognizedWord('Closing', 100.0, 40, 300, 40, 10),
        new RecognizedWord(':', 100.0, 83, 300, 3, 10),
        new RecognizedWord('10:30', 100.0, 95, 300, 28, 10),
    ];

    expect(app(KeyValueExtractor::class)->extract('Closing', $words)['value'])->toBe('10:30');
});

it('records the gutter it crossed as structural evidence', function (): void {
    $signals = app(KeyValueExtractor::class)->extract('Ministry', egpNoticeLine())['signals'];

    // From the right edge of the label word to the left edge of the first word
    // of the value: (154 - 81) / 11.
    expect(round($signals->gapInLabelHeights, 2))->toBe(6.64)
        ->and($signals->valueWordCount)->toBe(3)
        ->and($signals->competingLabelsOnLine)->toBe(2);
});
