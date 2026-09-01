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

it('reads a precedent as a lawyer cites one', function (): void {
    // Volume, reporter, division, page — the address of a decision in a
    // printed law report, and how a judgment points at the authority it relies
    // on. 1,196 of them across 293 documents.
    expect(tokensOfKind('reported in 28 DLR (AD) 38, wherein their lordships held', 'law_report_citation'))
        ->toBe(['28 DLR (AD) 38'])
        ->and(tokensOfKind('Society [54 DLR (AD) 111] the Apex Court observed', 'law_report_citation'))
        ->toBe(['54 DLR (AD) 111']);
});

it('separates citations a judgment chains together', function (): void {
    // Judgments give the same decision several addresses at once, joined by
    // equals signs. Each is a citation in its own right.
    expect(tokensOfKind('1986 6 BLD (AD) 328= 6 BCR (1986) (AD) 174=38 DLR (AD) 254 wherein', 'law_report_citation'))
        ->toBe(['6 BLD (AD) 328', '6 BCR (1986) (AD) 174', '38 DLR (AD) 254']);
});

it('names the reporters rather than matching any abbreviation', function (): void {
    // A generic pattern for a capitalised abbreviation between two numbers
    // found "OF" 163 times, out of "No. 8951 OF 2025", and "PW" 16 times, out
    // of "prosecution witness 3". Naming the reports says plainly which ones
    // this recognises.
    expect(tokensOfKind('WRIT PETITION NO. 8951 OF 2025 filed by PW 3 and PW 12 today', 'law_report_citation'))
        ->toBe([]);
});
