<?php

use App\Services\Extraction\ReviewCandidateGenerator;

/** @return list<string> */
function tokensOfKind(string $text, string $kind): array
{
    return array_values(array_map(
        fn (array $token): string => $token['value'],
        array_filter(
            app(ReviewCandidateGenerator::class)->tokens($text),
            fn (array $token): bool => $token['kind'] === $kind,
        ),
    ));
}

it('reads the identifier a judgment gives itself', function (): void {
    // A judgment names itself in its first lines, and that identifier is how a
    // case is cited, looked up and tracked. 401 of 563 read judgments carry
    // one in this form.
    expect(tokensOfKind('HIGH COURT DIVISION WRIT PETITION NO. 8951 OF 2025 IN THE MATTER OF:', 'case_number'))
        ->toBe(['WRIT PETITION NO. 8951 OF 2025'])
        ->and(tokensOfKind('Present: Mr. Justice X Criminal Revision No.204 of 2007 against', 'case_number'))
        ->toBe(['Criminal Revision No.204 of 2007']);
});

it('joins a citation that runs across a line break', function (): void {
    // Court citations break across lines more often than not, and the locator
    // matches a run of recognised words, which carry no newlines. Left as
    // captured, every one of these would be unlocatable by construction and
    // defer by rule without anyone learning why.
    expect(tokensOfKind("filed Writ Petition No. 9477 of\n2016 before this Court.", 'case_number'))
        ->toBe(['Writ Petition No. 9477 of 2016'])
        ->and(tokensOfKind("under Article 102 of\nthe Constitution, the petitioner", 'statutory_reference'))
        ->toBe(['Article 102 of the Constitution']);
});

it('reads the provision a judgment invokes', function (): void {
    // This is what makes registering BD Laws worth its plaintext exception: a
    // citation stops being a string and becomes resolvable.
    expect(tokensOfKind('An Appeal under Section 410 of the Code of Criminal Procedure, 1898 was filed.', 'statutory_reference'))
        ->toBe(['Section 410 of the Code of Criminal Procedure'])
        ->and(tokensOfKind('an application under Article 102(2)(a)(ii) of the Constitution and others', 'statutory_reference'))
        ->toBe(['Article 102(2)(a)(ii) of the Constitution']);
});

it('does not let the generic reference pattern claim a case number first', function (): void {
    // "No. 8951 of 2025" would otherwise be harvested as a bare reference, and
    // the case would be identified by a fragment of itself.
    $tokens = app(ReviewCandidateGenerator::class)->tokens('WRIT PETITION NO. 8951 OF 2025 filed today');

    $kinds = array_column($tokens, 'kind', 'value');

    expect($kinds)->toHaveKey('WRIT PETITION NO. 8951 OF 2025')
        ->and($kinds['WRIT PETITION NO. 8951 OF 2025'])->toBe('case_number');
});

it('leaves ordinary audit prose alone', function (): void {
    // The corpus is mostly audit reports, and a pattern that fired on them
    // would put court furniture into every other publisher's queue.
    $text = 'মোট ৩,৯৮,৭৪৮ টাকা ব্যয় হয়েছে, চালান নং-১২৩ তাং-১৩/১০/২০১১';

    expect(tokensOfKind($text, 'case_number'))->toBe([])
        ->and(tokensOfKind($text, 'statutory_reference'))->toBe([]);
});
