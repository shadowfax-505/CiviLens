<?php

use App\Services\Statistics\ClopperPearson;

/**
 * Checked against the published values for the exact binomial interval, because
 * an interval that is merely plausible is worse than none: it would put a
 * number in the write-up that nobody can reproduce.
 */
it('matches the published exact binomial intervals', function (int $k, int $n, float $low, float $high): void {
    $interval = app(ClopperPearson::class)->interval($k, $n);

    expect(round($interval['low'], 4))->toBe($low)
        ->and(round($interval['high'], 4))->toBe($high);
})->with([
    [0, 10, 0.0, 0.3085],
    [1, 10, 0.0025, 0.4450],
    [5, 10, 0.1871, 0.8129],
    [10, 10, 0.6915, 1.0],
]);

it('returns the whole range when nothing was observed', function (): void {
    // Any narrower claim would be invented.
    expect(app(ClopperPearson::class)->interval(0, 0))->toBe(['low' => 0.0, 'high' => 1.0]);
});

it('never reports a bound outside zero and one', function (): void {
    // Where a normal approximation fails: at these counts and proportions a
    // Wald interval reaches below zero and reports a lower bound that cannot
    // happen.
    foreach ([[0, 5], [1, 500], [12, 333], [333, 333]] as [$k, $n]) {
        $interval = app(ClopperPearson::class)->interval($k, $n);

        expect($interval['low'])->toBeGreaterThanOrEqual(0.0)
            ->and($interval['high'])->toBeLessThanOrEqual(1.0)
            ->and($interval['low'])->toBeLessThanOrEqual($interval['high']);
    }
});

it('widens as the sample shrinks', function (): void {
    $wide = app(ClopperPearson::class)->interval(1, 20);
    $narrow = app(ClopperPearson::class)->interval(50, 1000);

    expect($wide['high'] - $wide['low'])->toBeGreaterThan($narrow['high'] - $narrow['low']);
});

it('refuses a count that cannot happen', function (): void {
    expect(fn () => app(ClopperPearson::class)->interval(11, 10))
        ->toThrow(InvalidArgumentException::class);
});

it('says when a split cannot confirm the guarantee it reports', function (): void {
    // The conformal bound is over the draw, not over one test set. A realized
    // rate inside alpha is weak evidence when the interval around it reaches
    // well past alpha, and on this corpus that is every group: sixty labels put
    // the upper bound near 11% against a claimed 5%.
    //
    // Flagged so a favourable point estimate is not read as a confirmation the
    // sample cannot give.
    $interval = app(ClopperPearson::class)->interval(2, 60);

    expect($interval['high'])->toBeGreaterThan(0.05)
        ->and($interval['low'])->toBeLessThan(0.05);
});

it('says what a sample would have to be to confirm the guarantee', function (): void {
    // Measured rather than guessed. At the 3% error rate this corpus shows, the
    // upper bound first falls under alpha at about 500 held-out labels — 400 is
    // not enough and 500 is. The current bn split has 60.
    //
    // So confirming the guarantee empirically is roughly eight times the
    // adjudication already done, which is a cost worth knowing before anyone
    // plans to pay it.
    expect(app(ClopperPearson::class)->interval(12, 400)['high'])->toBeGreaterThan(0.05)
        ->and(app(ClopperPearson::class)->interval(15, 500)['high'])->toBeLessThan(0.05);
});
