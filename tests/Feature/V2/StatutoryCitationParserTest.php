<?php

use App\Services\Statutes\ParsedCitation;
use App\Services\Statutes\StatutoryCitationParser;

function citation(string $raw): ParsedCitation
{
    return app(StatutoryCitationParser::class)->parse($raw);
}

it('reads the provision and the instrument a citation names', function (): void {
    // The shape all 7,874 stored values take: keyword, number, "of the",
    // instrument. This is what makes a citation resolvable rather than a string.
    $citation = citation('Section 410 of the Code of Criminal Procedure');

    expect($citation->isResolvable())->toBeTrue()
        ->and($citation->provisionType)->toBe('section')
        ->and($citation->provisionNumber)->toBe('410')
        ->and($citation->instrumentName)->toBe('Code of Criminal Procedure')
        ->and($citation->instrumentKey)->toBe('code-of-criminal-procedure')
        ->and($citation->matchKey())->toBe('code-of-criminal-procedure/section-410');
});

it('keeps a sub-provision with the provision it qualifies', function (): void {
    // 2,118 of the corpus values carry one, and a threshold applied to
    // "Section 5" when the page said "Section 5(2)" would be pointed at a
    // different provision of the same Act.
    expect(citation('Section 5(2) of the Limitation Act')->provisionLabel())->toBe('5(2)')
        ->and(citation('Section 5(2) of the Limitation Act')->matchKey())->toBe('limitation-act/section-5-2')
        ->and(citation('Article 102(2)(a)(ii) of the Constitution')->subProvisions)->toBe(['2', 'a', 'ii'])
        ->and(citation('Article 102(2)(a)(ii) of the Constitution')->provisionLabel())->toBe('102(2)(a)(ii)');
});

it('reads one provision key for a Bengali clause written in either case', function (): void {
    // The corpus writes the same transliterated clause as '(ka)' 27 times and
    // '(Ka)' 21. Two keys would be two provisions, and neither would hold half
    // the citations of the one that exists.
    expect(citation('Section 43(1)(ka) of the Artha Rin Adalat Ain')->matchKey())
        ->toBe(citation('Section 43(1)(Ka) of the Artha Rin Adalat Ain')->matchKey())
        ->and(citation('Section 43(1)(Ka) of the Artha Rin Adalat Ain')->provisionLabel())->toBe('43(1)(Ka)');
});

it('keeps the letter suffix that makes a provision a different provision', function (): void {
    // 340 values carry one. Section 265H of the Code of Criminal Procedure is
    // not section 265, and dropping the H would resolve to the wrong text.
    expect(citation('Section 265H of the Code of Criminal Procedure')->provisionNumber)->toBe('265H')
        ->and(citation('Section 265H of the Code of Criminal Procedure')->matchKey())
        ->toBe('code-of-criminal-procedure/section-265h')
        ->and(citation('Section 561A of the Code of Criminal Procedure')->provisionNumber)->toBe('561A');
});

it('refuses a citation whose instrument is only the one named earlier on the page', function (): void {
    // 912 values. "the Code" is genuinely ambiguous in this corpus: it cites the
    // Code of Civil Procedure 1,431 times and the Code of Criminal Procedure 425,
    // in the same documents. Guessing either would be a resolution to the wrong
    // instrument, which is worse than none — but the provision was still read,
    // and so was the year where the page gave one, so both are kept on the
    // refusal.
    $bare = citation('Section 151 of the Code');

    expect($bare->isResolvable())->toBeFalse()
        ->and($bare->reason)->toBe(ParsedCitation::REASON_ANAPHORIC_INSTRUMENT)
        ->and($bare->matchKey())->toBeNull()
        ->and($bare->provisionType)->toBe('section')
        ->and($bare->provisionNumber)->toBe('151');

    $dated = citation('Section 138 of the Act, 1881 in filing the case');

    expect($dated->reason)->toBe(ParsedCitation::REASON_ANAPHORIC_INSTRUMENT)
        ->and($dated->instrumentYear)->toBe(1881)
        ->and($dated->matchKey())->toBeNull();

    expect(citation('Rule 11 of the Rules')->reason)->toBe(ParsedCitation::REASON_ANAPHORIC_INSTRUMENT)
        ->and(citation('Section 6 of the Ordinance')->reason)->toBe(ParsedCitation::REASON_ANAPHORIC_INSTRUMENT)
        ->and(citation('Order 11 of the Code')->reason)->toBe(ParsedCitation::REASON_ANAPHORIC_INSTRUMENT);
});

it('resolves a bare Constitution and no other bare instrument noun', function (): void {
    // The asymmetry is in the data, not a convenience. Bangladesh has one
    // Constitution and the corpus writes every foreign one out in full —
    // "Constitution of India" 15, "Indian Constitution" 13, "Constitution of
    // Pakistan" 3 — so the 990 bare mentions denote uniquely. Refusing them for
    // symmetry with "the Code" would reject the most cited instrument in the
    // corpus over an ambiguity it does not have.
    expect(citation('Article 102 of the Constitution')->matchKey())->toBe('constitution/article-102')
        ->and(citation('Article 173 of the Constitution of the Republic of Pakistan')->instrumentKey)
        ->toBe('constitution-of-the-republic-of-pakistan')
        ->and(citation('Section 151 of the Code')->isResolvable())->toBeFalse();
});

it('ends the instrument name where the prose resumes', function (): void {
    // The candidate pattern ends a match at [,;.] or at " and", and where
    // neither fired the sentence ran on into the value: "Code of Civil Procedure
    // this Rule" 93 times, "Act, 1881 in filing the case" 42. Left in, each tail
    // would key as its own instrument.
    expect(citation('Article 102 of the Constitution this Rule Nisi')->instrumentKey)->toBe('constitution')
        ->and(citation('Rule 5 of the Code of Civil Procedure read with sections 44')->instrumentKey)
        ->toBe('code-of-civil-procedure')
        ->and(citation('Section 96 of the Trademarks Act for alternative')->instrumentKey)->toBe('trademarks-act')
        ->and(citation('Article 102 of the Constitution the High Court Division')->instrumentKey)->toBe('constitution');

    // "and" joins a name on the way to its noun — "State Acquisition and Tenancy
    // Act" — and after the noun it is the sentence carrying on. Read the other
    // way, this citation of the Act resolves to an instrument called "Act and
    // the Seventh Schedule", which does not exist.
    $swept = citation('Section 100 of the Act and the Seventh Schedule to it show how the division has');

    expect($swept->instrumentName)->toBe('Act')
        ->and($swept->reason)->toBe(ParsedCitation::REASON_ANAPHORIC_INSTRUMENT);
});

it('refuses a name a stray the has broken, and keeps one a connective carries on', function (): void {
    // Both of these are stored values. "of the Limitation the Act" is the page
    // damaged: read through the stray "the" it keys to 'limitation-the-act', an
    // instrument that does not exist and that would never reach the Limitation
    // Act the corpus cites 200 times. And a capitalised "The" that no connective
    // introduced is a new sentence, not more of the title — one value repeats
    // its own instrument and would otherwise key to it twice over.
    expect(citation('Article 164 of the Limitation the Act')->reason)
        ->toBe(ParsedCitation::REASON_UNNAMED_INSTRUMENT)
        ->and(citation('Section 164 of the Code of Criminal Procedure The Code of Criminal Procedure')->instrumentKey)
        ->toBe('code-of-criminal-procedure')
        // The same word behind a connective is part of the name.
        ->and(citation('Article 102 of the Constitution of the Peoples Republic of Bangladesh')->instrumentKey)
        ->toBe('constitution-of-the-peoples-republic-of-bangladesh');
});

it('keeps a whole instrument name that reaches its noun through and', function (): void {
    expect(citation('Section 3 of the State Acquisition and Tenancy Act')->instrumentKey)
        ->toBe('state-acquisition-and-tenancy-act')
        ->and(citation('Section 4 of the Carriage of Goods by Sea Act')->instrumentKey)
        ->toBe('carriage-of-goods-by-sea-act');
});

it('refuses a phrase that names no instrument rather than keying on it', function (): void {
    // Two causes and the same answer. The candidate pattern's " and" lookahead
    // truncated 148 values to "State Acquisition" before they reached the
    // database, and OCR damaged the rest. Neither can be repaired from the value.
    foreach ([
        'Section 152 of the State Acquisition',
        'Article 12 of the Articles of Association',
        'Article 128 of the ConstitutiOQ',
        'Section 3A of the Comptroller',
        'Section 12 of the First Schedule',
    ] as $raw) {
        expect(citation($raw)->reason)->toBe(ParsedCitation::REASON_UNNAMED_INSTRUMENT)
            ->and(citation($raw)->matchKey())->toBeNull();
    }
});

it('keeps the year off the matching key and in its own field', function (): void {
    // Only 427 of 7,874 values state a year — the candidate pattern's comma
    // lookahead cuts "Code of Criminal Procedure, 1898" short of it — so keying
    // on one would split an instrument into a dated and an undated half and
    // match neither. "Artha Rin Adalat Ain" appears 50 times without a year and
    // once with.
    $dated = citation('Section 60 of the Artha Rin Adalat Ain 2003');
    $bare = citation('Section 60 of the Artha Rin Adalat Ain');

    expect($dated->instrumentKey)->toBe($bare->instrumentKey)
        ->and($dated->matchKey())->toBe($bare->matchKey())
        ->and($dated->instrumentYear)->toBe(2003)
        ->and($bare->instrumentYear)->toBeNull()
        ->and(citation('Article 212 of the Government of India Act 1935')->instrumentKey)
        ->toBe('government-of-india-act');
});

it('reads a citation that was set across a line break', function (): void {
    // The candidate generator joins these before storing, so no stored value
    // carries a newline. The parser is also handed page text directly, where a
    // citation broken over a line and a word broken by a hyphen are both
    // ordinary.
    expect(citation("Article 102 of\nthe Constitution")->matchKey())->toBe('constitution/article-102')
        ->and(citation("Section 5 of the Code of Civil Proce-\ndure")->instrumentKey)
        ->toBe('code-of-civil-procedure')
        ->and(citation("Section  151   of the\u{00a0}Penal Code")->instrumentKey)->toBe('penal-code');
});

it('reads an instrument cited only by its initials, and expands nothing', function (): void {
    // "CPC" is the Code of Civil Procedure to any Bangladeshi lawyer, and
    // deciding that inside a parser would put a judgement where nobody could
    // inspect it. The key is what the page said; the expansion is a curated fact
    // on the statute row.
    expect(citation('Order 41 of the CPC')->instrumentKey)->toBe('cpc')
        ->and(citation('rule 98 of PPR 2008')->instrumentKey)->toBe('ppr')
        ->and(citation('rule 98 of PPR 2008')->instrumentYear)->toBe(2008)
        ->and(citation('Rule 13 of the B.F.P')->instrumentKey)->toBe('b-f-p');

    // An initialism carries its year and nothing else, so an ordinary
    // capitalised word after one is still the sentence.
    expect(citation('Section 30 of the ICSID Convention')->reason)
        ->toBe(ParsedCitation::REASON_UNNAMED_INSTRUMENT);
});

it('reports a provision it read with no instrument after it', function (): void {
    $citation = citation('Article 111');

    expect($citation->reason)->toBe(ParsedCitation::REASON_NO_INSTRUMENT)
        ->and($citation->provisionType)->toBe('article')
        ->and($citation->provisionNumber)->toBe('111')
        ->and($citation->matchKey())->toBeNull();
});

it('refuses a provision number it cannot read rather than approximating one', function (): void {
    // Roman-numeral orders are how the Code of Civil Procedure numbers its own
    // Orders. The corpus holds none, because the candidate pattern cannot
    // produce one, and inventing a mapping for a form nothing has been measured
    // on is the mistake this parser was written after.
    expect(citation('Order XLI Rule 1 of the Code of Civil Procedure')->reason)
        ->toBe(ParsedCitation::REASON_NO_PROVISION)
        ->and(citation('Order XLI Rule 1 of the Code of Civil Procedure')->provisionNumber)->toBeNull()
        ->and(citation('the Code of Civil Procedure')->reason)->toBe(ParsedCitation::REASON_NO_PROVISION);
});

it('parses the real corpus values it was built from', function (string $raw, ?string $key): void {
    // Sampled from `extraction_fields` rather than composed, because two field
    // kinds were added to this pipeline without checking the real values and
    // located 0% of the time. Each of these is a string a High Court judgment
    // actually carries.
    expect(citation($raw)->matchKey())->toBe($key);
})->with([
    ['Section 115(1) of the Code of Civil Procedure', 'code-of-civil-procedure/section-115-1'],
    ['Article 102(2)(a)(ii) of the Constitution of the Peoples Republic of Bangladesh', 'constitution-of-the-peoples-republic-of-bangladesh/article-102-2-a-ii'],
    ['Section 3 of the Value Added Tax Act', 'value-added-tax-act/section-3'],
    ['Article 1009 of the Civil Service Regulations', 'civil-service-regulations/article-1009'],
    ['Section 43(1)(ka) of the Nari-O-Shishu Nirjatan Daman Ain', 'nari-o-shishu-nirjatan-daman-ain/section-43-1-ka'],
    ['Section 50 of the Waqfs Ordinance', 'waqfs-ordinance/section-50'],
    ['Section 19 of the Narcotics Control Act 1990', 'narcotics-control-act/section-19'],
    ['Rule 29 of the Tenancy Rules', 'tenancy-rules/rule-29'],
    ['Section 420 of the Penal Code', 'penal-code/section-420'],
    ['Article 48(3) of the Constitution', 'constitution/article-48-3'],
    ['Section 152 of the State Acquisition', null],
    ['Section 164 of the Code after complying with all legal formalities', null],
    // Spelled out, "Articles of Association" names no instrument and is refused.
    // Abbreviated it cannot be tested against anything, so it keys on what the
    // page said and goes unmatched at the statute table instead — the same
    // asymmetry that lets CPC through, and the price of it.
    ['Article 41 of the AoA of the club', 'aoa/article-41'],
    ['Rule 40 of the S.R.O', 's-r-o/rule-40'],
]);
