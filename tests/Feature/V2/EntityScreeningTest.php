<?php

use App\Models\ScreeningEntity;
use App\Models\SourceArtifactVersion;
use App\Services\Screening\EntityNameNormaliser;
use App\Services\Screening\EntityScreener;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function screeningEntity(string $name, string $countries = 'bd'): ScreeningEntity
{
    $artifact = SourceArtifactVersion::factory()->create(['media_type' => 'text/csv']);

    return ScreeningEntity::query()->create([
        'source_artifact_version_id' => $artifact->id,
        'dataset' => 'worldbank_debarred',
        'external_id' => 'x-'.md5($name),
        'entity_type' => 'LegalEntity',
        'name' => $name,
        'normalized_name' => app(EntityNameNormaliser::class)->normalise($name),
        'aliases' => [],
        'countries' => $countries,
        'topics' => 'debarment',
    ]);
}

it('resolves the ways one firm is written across documents', function (): void {
    // The same firm appears as "M/S ... LIMITED" in a tender and
    // "... & ... LTD." on a debarment list. Matching those is the whole job.
    $normaliser = app(EntityNameNormaliser::class);
    $canonical = $normaliser->normalise('SRS DESIGN & FASHION LTD.');

    expect($normaliser->normalise('M/S SRS DESIGN AND FASHION LIMITED'))->toBe($canonical)
        ->and($normaliser->normalise('srs design & fashion ltd'))->toBe($canonical)
        ->and($normaliser->normalise('S.R.S. Design & Fashion'))->not->toBe($canonical);
});

it('keeps a name that is nothing but legal form rather than emptying it', function (): void {
    // An emptied name would match every other emptied name, which is the worst
    // possible failure for a screening tool.
    expect(app(EntityNameNormaliser::class)->normalise('The Company Ltd'))->not->toBe('');
});

it('finds a debarred entity through a variant spelling', function (): void {
    screeningEntity('SRS DESIGN & FASHION LTD.');

    $hit = app(EntityScreener::class)->candidates('M/S SRS Design and Fashion Limited')->first();

    expect($hit['method'])->toBe('exact-normalised-name')
        ->and($hit['score'])->toBe(1.0)
        // The name as published, never the normalised form: the differences it
        // discards are sometimes what distinguishes two firms.
        ->and($hit['entity']->name)->toBe('SRS DESIGN & FASHION LTD.');
});

it('returns nothing for a name that is not on the list', function (): void {
    // A screening tool that always finds something is a screening tool nobody
    // can act on.
    screeningEntity('SRS DESIGN & FASHION LTD.');

    expect(app(EntityScreener::class)->candidates('Dhaka Metro Rail Development Authority'))->toBeEmpty();
});

it('offers a near miss as a scored candidate rather than a match', function (): void {
    // Firm names repeat. Anything short of exact is for a person to judge with
    // both records in front of them.
    screeningEntity('RAHMAN CONSTRUCTION AND ENGINEERING WORKS LTD');

    $hit = app(EntityScreener::class)->candidates('Rahman Construction Works')->first();

    expect($hit)->not->toBeNull()
        ->and($hit['method'])->toBe('token-overlap')
        ->and($hit['score'])->toBeLessThan(1.0);
});

it('does not offer a pair that merely shares a common word', function (): void {
    // "National" and "construction" appear in hundreds of firm names; scoring
    // them as candidates would bury the real ones.
    screeningEntity('NATIONAL POWER SUPPLY CORPORATION');

    expect(app(EntityScreener::class)->candidates('National Fisheries Development Board'))->toBeEmpty();
});
