<?php

use App\Services\Extraction\SecondReadScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Reach the private rule the scorer applies before it computes a score. */
function mixesScripts(string $value): bool
{
    $method = new ReflectionMethod(SecondReadScorer::class, 'mixesScripts');

    return $method->invoke(app(SecondReadScorer::class), $value);
}

it('treats a numeral written in two alphabets as a reading artefact', function (): void {
    // A document writes a number in a script. It does not write ৫8,8৪8.
    //
    // The ones that certified were not harmless: 1১৪০৭৭.৩৩ was read [১৪০৭৭.৩৩
    // on the second pass — a table rule line taken for a leading digit by one
    // and a bracket by the other, so the certified figure was ten times the one
    // on the page. Edit distance cannot see it: the strings differ in one
    // character and that character is the most significant digit.
    expect(mixesScripts('1১৪০৭৭.৩৩'))->toBeTrue()
        ->and(mixesScripts('৫8,8৪8'))->toBeTrue()
        ->and(mixesScripts('8৫১৭.৪'))->toBeTrue();
});

it('leaves a number written in one script alone', function (): void {
    // Including Latin punctuation, currency marks and units beside Bengali
    // digits — what is disallowed is one *number* spelled in two alphabets.
    expect(mixesScripts('১,২৩,৪৫৬'))->toBeFalse()
        ->and(mixesScripts('123,456.78'))->toBeFalse()
        ->and(mixesScripts('৳১২৩'))->toBeFalse()
        ->and(mixesScripts('১২৩/-'))->toBeFalse();
});

it('bumps the reader version, because a stale score now means something else', function (): void {
    // A score is a claim about how a reader behaved. Changing the deferral
    // rules changes what every earlier score means, and health has to be able
    // to say how much of the corpus is owed a re-read.
    expect(SecondReadScorer::READER_VERSION)->toBe('r3');
});
