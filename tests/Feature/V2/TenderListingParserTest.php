<?php

use App\Services\Ingestion\TenderListingParser;

function listingFixture(): string
{
    return (string) file_get_contents(__DIR__.'/../../Fixtures/Ingestion/egp-tender-rows.html');
}

it('parses every row of a real publisher response', function (): void {
    $rows = (new TenderListingParser)->parse(listingFixture());

    expect($rows)->toHaveCount(10)
        ->and($rows[0]->tenderId)->toBe('1316153')
        ->and($rows[0]->referenceNumber)->toBe('37.07.0000.000.014.20.101.24-7311-1')
        ->and($rows[0]->status)->toBe('Live')
        ->and($rows[0]->procurementNature)->toBe('Works')
        ->and($rows[0]->publishedOn)->toBe('07-07-26');
});

it('keeps the date out of the reference number', function (): void {
    foreach ((new TenderListingParser)->parse(listingFixture()) as $row) {
        expect(mb_strtolower($row->referenceNumber))->not->toContain('date');
    }
});

it('accepts the several date formats this publisher writes', function (): void {
    $parser = new TenderListingParser;

    $rows = $parser->parse(
        '<tr><td>1</td><td>111111,<br/>REF-A, Date 07-07-26,<br/>Live</td><td>Works,</td></tr>'
        .'<tr><td>2</td><td>222222,<br/>REF-B, Date-01.08.2026,<br/>Live</td><td>Goods,</td></tr>'
        .'<tr><td>3</td><td>333333,<br/>REF-C, Date 3/12/2026,<br/>Archived</td><td>Services,</td></tr>'
    );

    expect(array_map(fn ($r): ?string => $r->publishedOn, $rows))->toBe(['07-07-26', '01.08.2026', '3/12/2026'])
        ->and(array_map(fn ($r): string => $r->referenceNumber, $rows))->toBe(['REF-A', 'REF-B', 'REF-C']);
});

it('skips a row rather than guessing at a malformed identifier', function (): void {
    // A wrong tender id is worse than a missing one: it would attach real
    // evidence to the wrong procurement.
    $rows = (new TenderListingParser)->parse(
        '<tr><td>1</td><td>not-an-id,<br/>REF-A,<br/>Live</td><td>Works</td></tr>'
        .'<tr><td>2</td><td>12,<br/>REF-B,<br/>Live</td><td>Works</td></tr>'
        .'<tr><td>3</td><td>999999,<br/>REF-C,<br/>Live</td><td>Works</td></tr>'
    );

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->tenderId)->toBe('999999');
});

it('treats publisher markup as untrusted text', function (): void {
    $rows = (new TenderListingParser)->parse(
        '<tr><td>1</td><td>456789,<br/><script>alert(1)</script>REF&amp;X,<br/>Live</td><td>Works</td></tr>'
    );

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->referenceNumber)->not->toContain('<script')
        ->and($rows[0]->referenceNumber)->toContain('REF&X');
});

it('bounds field length so a hostile row cannot bloat a record', function (): void {
    $rows = (new TenderListingParser)->parse(
        '<tr><td>1</td><td>456789,<br/>'.str_repeat('A', 5000).',<br/>Live</td><td>Works</td></tr>'
    );

    expect(mb_strlen($rows[0]->referenceNumber))->toBeLessThanOrEqual(300);
});

it('returns nothing for empty or unrelated markup', function (): void {
    $parser = new TenderListingParser;

    expect($parser->parse(''))->toBe([])
        ->and($parser->parse('<html><body><p>no table here</p></body></html>'))->toBe([])
        ->and($parser->parse('<tr><td>only one cell</td></tr>'))->toBe([]);
});
