<?php

use App\Services\Extraction\SecondReadScorer;

it('documents every reason a value can carry instead of a score', function (): void {
    // A deferral is part of what the guarantee covers: the bound holds over
    // what is accepted, and every rule here shrinks that set. Adding one
    // silently would narrow the claim without narrowing the claim's
    // description, which is the failure this project keeps finding in itself —
    // the numbers stayed true and the thing they were true of got smaller.
    $card = (string) file_get_contents(base_path('docs/METHOD-CARD.md'));

    foreach (SecondReadScorer::SCORE_BASES as $basis) {
        expect($card)->toContain('`'.$basis.'`');
    }
});

it('emits no basis it has not declared', function (): void {
    // The declaration is what the card is checked against, so it has to match
    // the code rather than the author's memory of it.
    $source = (string) file_get_contents(app_path('Services/Extraction/SecondReadScorer.php'));

    preg_match_all("/'score_basis' => '([a-z-]+)'/", $source, $matches);

    expect(array_values(array_unique($matches[1])))
        ->each->toBeIn(SecondReadScorer::SCORE_BASES);
});
