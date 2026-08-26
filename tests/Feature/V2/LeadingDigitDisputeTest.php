<?php

use App\Services\Extraction\SecondReadScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function leadingDigitDisputed(string $first, string $second): bool
{
    $method = new ReflectionMethod(SecondReadScorer::class, 'leadingDigitDisputed');

    return $method->invoke(app(SecondReadScorer::class), $first, $second);
}

it('defers when the two readings disagree about which order of magnitude the number is', function (): void {
    // The disagreement that matters most is the one edit distance weighs least.
    // ১০০০০ against ৯০০০০ is ten thousand against ninety thousand and scores
    // one character in five; both readings certified.
    expect(leadingDigitDisputed('১০০০০', '৯০০০০'))->toBeTrue()
        ->and(leadingDigitDisputed('400.00', '800.00'))->toBeTrue()
        ->and(leadingDigitDisputed('৪৫০০.০০', '8৫০০.০০'))->toBeTrue();
});

it('defers when one reading carries a leading digit the other does not', function (): void {
    // 39656.49 against 639656.49 is a sixteenfold difference. The extra
    // character is often a table rule line the recognizer took for a digit.
    expect(leadingDigitDisputed('39656.49', '639656.49'))->toBeTrue()
        ->and(leadingDigitDisputed('৩৩৫৫৪', '২৩৩৫৫৪'))->toBeTrue()
        ->and(leadingDigitDisputed('৮১৪৩২.৩৩', '1৮১৪৩২.৩৩'))->toBeTrue();
});

it('does not treat noise picked up off the page as a dispute about the number', function (): void {
    // A second read that returns “3500.00 has caught a quotation mark in the
    // crop. That is a fact about the crop, not about the figure, and deferring
    // on it would abstain on values nothing is wrong with.
    expect(leadingDigitDisputed('3500.00', '“3500.00'))->toBeFalse()
        ->and(leadingDigitDisputed('৳১২৩', '্১২৩'))->toBeFalse();
});

it('leaves agreeing readings alone, including a currency terminator', function (): void {
    expect(leadingDigitDisputed('১,২৩,৪৫৬', '১,২৩,৪৫৬'))->toBeFalse()
        ->and(leadingDigitDisputed('৩,৯৮,৭৪৮', '৩,৯৮,৭৪৮/-'))->toBeFalse()
        // A disagreement anywhere but the front is what the score is for.
        ->and(leadingDigitDisputed('১২৩৪৫', '১২৩৪৯'))->toBeFalse();
});

it('bumps the reader version', function (): void {
    expect(SecondReadScorer::READER_VERSION)->toBe('r4');
});
