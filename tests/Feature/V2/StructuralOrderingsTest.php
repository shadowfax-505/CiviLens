<?php

use App\Data\Extraction\FieldExtractionSignals;
use App\Services\Extraction\StructuralOrderings;

function signals(
    bool $found = true,
    int $span = 1,
    float $gap = 0.5,
    int $valueWords = 2,
    bool $formatOk = true,
    int $competing = 0,
): FieldExtractionSignals {
    return new FieldExtractionSignals($found, $span, $gap, $valueWords, $formatOk, $competing);
}

it('offers every ordering it names', function (): void {
    $orderings = new StructuralOrderings;

    foreach ($orderings->names() as $name) {
        expect($orderings->score($name, signals()))->toBeFloat();
    }

    expect($orderings->names())->toHaveCount(4);
});

it('treats a missing label as maximally nonconforming in every ordering', function (): void {
    $orderings = new StructuralOrderings;
    $missing = signals(found: false);

    foreach ([StructuralOrderings::LABEL_EXACTNESS, StructuralOrderings::LAYOUT_DISTANCE, StructuralOrderings::FORMAT_CONFORMANCE] as $name) {
        expect($orderings->score($name, $missing))->toBe(1.0);
    }

    expect($orderings->score(StructuralOrderings::COMBINED, $missing))->toBe(1.0);
});

it('ranks a tightly matched label above a fragmented one', function (): void {
    $orderings = new StructuralOrderings;

    expect($orderings->score(StructuralOrderings::LABEL_EXACTNESS, signals(span: 1)))
        ->toBeLessThan($orderings->score(StructuralOrderings::LABEL_EXACTNESS, signals(span: 5)));
});

it('ranks a near value above a distant one and penalises a crowded line', function (): void {
    $orderings = new StructuralOrderings;

    expect($orderings->score(StructuralOrderings::LAYOUT_DISTANCE, signals(gap: 0.5)))
        ->toBeLessThan($orderings->score(StructuralOrderings::LAYOUT_DISTANCE, signals(gap: 15.0)))
        ->and($orderings->score(StructuralOrderings::LAYOUT_DISTANCE, signals(competing: 0)))
        ->toBeLessThan($orderings->score(StructuralOrderings::LAYOUT_DISTANCE, signals(competing: 3)));
});

it('ranks a well-formed value above a malformed one', function (): void {
    $orderings = new StructuralOrderings;

    expect($orderings->score(StructuralOrderings::FORMAT_CONFORMANCE, signals(formatOk: true)))
        ->toBeLessThan($orderings->score(StructuralOrderings::FORMAT_CONFORMANCE, signals(formatOk: false)));
});

it('keeps every score within the unit interval', function (): void {
    $orderings = new StructuralOrderings;
    $extremes = [
        signals(found: false),
        signals(span: 100, gap: 1000.0, competing: 50),
        signals(gap: -5.0, valueWords: 0),
    ];

    foreach ($orderings->names() as $name) {
        foreach ($extremes as $case) {
            $score = $orderings->score($name, $case);
            expect($score)->toBeGreaterThanOrEqual(0.0)->toBeLessThanOrEqual(1.0);
        }
    }
});

it('is a pure function of structure and never of the outcome', function (): void {
    // The same structural evidence must score identically regardless of whether
    // the extracted value later proves right. A score that varied with the
    // outcome would separate outcomes perfectly and certify by accident.
    $orderings = new StructuralOrderings;
    $a = signals(span: 2, gap: 3.0, formatOk: true);
    $b = signals(span: 2, gap: 3.0, formatOk: true);

    foreach ($orderings->names() as $name) {
        expect($orderings->score($name, $a))->toBe($orderings->score($name, $b));
    }
});

it('falls back to maximal nonconformity for an unknown ordering', function (): void {
    expect((new StructuralOrderings)->score('not_an_ordering', signals()))->toBe(1.0);
});
