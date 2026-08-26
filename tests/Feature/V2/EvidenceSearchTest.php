<?php

use App\Services\Publication\PublicationSnapshotBuilder;
use App\Services\PublicPortal\EvidenceSearchService;
use App\Support\BengaliNumerals;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function publishedFigure(string $value): void
{
    ['page' => $page] = publishableDocument();
    certifiedFieldOn($page, 'accepted', ['extracted_value' => $value]);

    $builder = app(PublicationSnapshotBuilder::class);
    $builder->publish($builder->build('published-'.uniqid()));
}

it('finds a figure typed in Bengali that the page stores in Latin', function (): void {
    // The gate. Legacy fonts in this corpus write Bengali numerals as the Latin
    // bytes that render them, so a reader who types what they can see on the
    // page gets nothing from a literal search and concludes the document is not
    // here.
    publishedFigure('419.6');

    $result = app(EvidenceSearchService::class)->search('৪১৯.৬');

    expect($result['figures'])->toHaveCount(1)
        ->and($result['figures']->first()->extracted_value)->toBe('419.6');
});

it('finds a figure typed in Latin that the page stores in Bengali', function (): void {
    publishedFigure('৪১৯.৬');

    expect(app(EvidenceSearchService::class)->search('419.6')['figures'])->toHaveCount(1);
});

it('ignores how the figure was grouped', function (): void {
    // ৫,১৪,০৫১ and ৫১৪,০৫১ are one amount separated two ways, and a reader
    // typing either means the same number. The separator still matters to
    // certification — a misplaced one is a hundredfold error — but not to
    // finding the thing.
    publishedFigure('৫১৪,০৫১');

    expect(app(EvidenceSearchService::class)->search('৫,১৪,০৫১')['figures'])->toHaveCount(1)
        ->and(app(EvidenceSearchService::class)->search('514051')['figures'])->toHaveCount(1);
});

it('shows the reader both forms it searched for', function (): void {
    // A search that silently rewrote the query and found nothing is
    // indistinguishable from one that looked for what was typed.
    publishedFigure('419.6');

    expect(app(EvidenceSearchService::class)->search('৪১৯.৬')['searched_as'])
        ->toContain('৪১৯.৬')
        ->toContain('419.6');
});

it('does not search values the system declined to stand behind', function (): void {
    // Searching deferred values would hand a reader figures the system withheld,
    // with none of the context that says so.
    ['page' => $page] = publishableDocument();
    certifiedFieldOn($page, 'deferred', ['extracted_value' => '৯৯৯,৯৯৯']);

    $builder = app(PublicationSnapshotBuilder::class);
    $builder->publish($builder->build('published'));

    expect(app(EvidenceSearchService::class)->search('৯৯৯,৯৯৯')['figures'])->toBeEmpty();
});

it('returns nothing for an empty query rather than everything', function (): void {
    publishedFigure('419.6');

    expect(app(EvidenceSearchService::class)->search('  ')['figures'])->toBeEmpty();
});

it('treats the two alphabets as one', function (): void {
    $numerals = app(BengaliNumerals::class);

    expect($numerals->toLatin('২১৬.৯'))->toBe('216.9')
        ->and($numerals->toBengali('216.9'))->toBe('২১৬.৯')
        ->and($numerals->digitsOnly('৫,১৪,০৫১'))->toBe('514051')
        ->and($numerals->digitsOnly('৫১৪,০৫১'))->toBe('514051')
        // Punctuation that carries meaning is kept: a separator in the wrong
        // place is a hundredfold error, and dropping it here would make two
        // different figures look identical.
        ->and($numerals->canonical('৳ ৫,১৪,০৫১/-'))->toBe('5,14,051-');
});
