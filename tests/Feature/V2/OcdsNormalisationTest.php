<?php

use App\Services\Normalisation\OcdsTenderNormaliser;

/** Labels and values as they are printed on a real e-GP notice. */
function noticeFields(array $overrides = []): array
{
    return array_merge([
        'Ministry' => 'Ministry of Education',
        'Organization' => 'Education Engineering Department',
        'District' => 'Bogura',
        'Procurement Nature' => 'Works',
        'Procurement Method' => 'Open Tendering Method',
        'Tender/Proposal ID' => '1316153',
        'App ID' => '229444',
    ], $overrides);
}

it('maps a notice onto open contracting fields', function (): void {
    $result = app(OcdsTenderNormaliser::class)->normalise(noticeFields());

    expect($result['release']['tender']['id'])->toBe('1316153')
        ->and($result['release']['tender']['procurementMethod'])->toBe('open')
        ->and($result['release']['tender']['mainProcurementCategory'])->toBe('works')
        ->and($result['release']['buyer']['name'])->toBe('Education Engineering Department')
        ->and($result['release']['buyer']['address']['region'])->toBe('Bogura');
});

it('keeps the raw value beside the normalised one', function (): void {
    // Normalisation is lossy and sometimes wrong. A reviewer has to be able to
    // see through "open" to the words the document actually carried.
    $result = app(OcdsTenderNormaliser::class)->normalise(noticeFields());

    expect($result['raw']['tender.procurementMethod'])->toBe('Open Tendering Method')
        ->and($result['raw']['tender.mainProcurementCategory'])->toBe('Works');
});

it('reports an unrecognised method rather than guessing the nearest code', function (): void {
    // procurementMethod is a closed codelist. Filing an unknown method as the
    // nearest match would make a tender look routine when it is an exception,
    // or an exception when it is routine.
    $result = app(OcdsTenderNormaliser::class)->normalise(
        noticeFields(['Procurement Method' => 'Some Method Nobody Has Coded'])
    );

    expect($result['release']['tender'])->not->toHaveKey('procurementMethod')
        ->and($result['unmapped_values'])->toHaveCount(1)
        ->and($result['unmapped_values'][0]['field'])->toBe('tender.procurementMethod')
        ->and($result['unmapped_values'][0]['value'])->toBe('Some Method Nobody Has Coded')
        // Still recoverable: the raw value is kept even when no code applies.
        ->and($result['raw']['tender.procurementMethod'])->toBe('Some Method Nobody Has Coded');
});

it('reports a label it has never seen rather than dropping it', function (): void {
    $result = app(OcdsTenderNormaliser::class)->normalise(
        noticeFields(['Some New Field The Publisher Added' => 'value'])
    );

    expect($result['unmapped_labels'])->toBe(['Some New Field The Publisher Added']);
});

it('builds an ocid from the tender identifier', function (): void {
    $result = app(OcdsTenderNormaliser::class)->normalise(noticeFields());

    expect($result['ocid'])->toBe('bd-egp-1316153');
});

it('refuses to invent an ocid when there is no tender identifier', function (): void {
    // An ocid identifies a contracting process across its whole life. Invented,
    // it creates a second process rather than identifying the existing one, and
    // every later record for that tender would fail to join to it.
    $fields = noticeFields();
    unset($fields['Tender/Proposal ID']);

    expect(app(OcdsTenderNormaliser::class)->normalise($fields)['ocid'])->toBeNull();
});

it('matches a label whatever punctuation the notice prints around it', function (): void {
    $result = app(OcdsTenderNormaliser::class)->normalise([
        'Procurement Nature :' => 'Works',
        'procurement  method' => 'Open Tendering Method',
    ]);

    expect($result['release']['tender']['mainProcurementCategory'])->toBe('works')
        ->and($result['release']['tender']['procurementMethod'])->toBe('open');
});

it('treats an empty value as unmapped rather than as a blank field', function (): void {
    $result = app(OcdsTenderNormaliser::class)->normalise(noticeFields(['District' => '']));

    expect($result['release']['buyer'] ?? [])->not->toHaveKey('address')
        ->and($result['unmapped_values'])->toHaveCount(1);
});

it('codes each procurement category the portal uses', function (): void {
    $normaliser = app(OcdsTenderNormaliser::class);

    foreach (['Works' => 'works', 'Goods' => 'goods', 'Services' => 'services'] as $printed => $code) {
        expect($normaliser->normalise(['Procurement Nature' => $printed])['release']['tender']['mainProcurementCategory'])
            ->toBe($code);
    }
});
