<?php

use App\Models\ExtractionField;
use App\Models\ScreeningEntity;
use App\Models\SourceArtifactVersion;
use App\Services\Screening\CorpusScreeningReport;
use App\Services\Screening\EntityNameNormaliser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// No factory for these — they arrive from a published list, not from a seeder.
function listedEntity(string $name): ScreeningEntity
{
    return ScreeningEntity::query()->create([
        'source_artifact_version_id' => SourceArtifactVersion::factory()->create()->id,
        'dataset' => 'worldbank_debarred',
        'external_id' => (string) Str::uuid(),
        'entity_type' => 'company',
        'name' => $name,
        'normalized_name' => app(EntityNameNormaliser::class)->normalise($name),
    ]);
}

it('reports a name that resembles a listed entity as a resemblance', function (): void {
    // Candidates, never verdicts. The claim a match supports is that two
    // strings resemble each other, and a person decides whether they are the
    // same firm.
    ExtractionField::factory()->create([
        'field_key' => 'organisation',
        'extracted_value' => 'M/S SRS DESIGN AND FASHION LIMITED',
    ]);

    listedEntity('SRS DESIGN & FASHION LTD.');

    $report = app(CorpusScreeningReport::class)->build();

    expect($report['names_resembling_a_listed_entity'])->toBe(1)
        ->and($report['what_a_match_means'])->toContain('judgement this system does not make');
});

it('does not report a name with no resemblance as a match', function (): void {
    // The screener returns a Collection, so an identity check against [] is
    // always false — every name looked like a match, with an empty list of what
    // it matched. A report that lists 39 matches and 0 matched entities is
    // worse than one that finds nothing.
    ExtractionField::factory()->create([
        'field_key' => 'organisation',
        'extracted_value' => 'মেসার্স তাজরি সি ফুডস্',
    ]);

    listedEntity('SOMETHING ENTIRELY DIFFERENT INC');

    $report = app(CorpusScreeningReport::class)->build();

    expect($report['names_found_in_the_corpus'])->toBe(1)
        ->and($report['names_resembling_a_listed_entity'])->toBe(0)
        ->and($report['matches'])->toBe([]);
});

it('says what a zero means rather than leaving it to be assumed', function (): void {
    // Absence from one register assembled for a different purpose, in which
    // very few Bangladeshi firms appear at all. A reader who takes that for
    // exoneration has been misled by the report rather than by the data.
    ExtractionField::factory()->create([
        'field_key' => 'organisation',
        'extracted_value' => 'মেসার্স সিমার্ক',
    ]);

    expect(app(CorpusScreeningReport::class)->build()['what_a_zero_means'])
        ->toContain('not a finding about any contractor');
});

it('screens only organisation candidates', function (): void {
    ExtractionField::factory()->create(['field_key' => 'amount', 'extracted_value' => '১,২৩,৪৫৬']);

    expect(app(CorpusScreeningReport::class)->build()['names_found_in_the_corpus'])->toBe(0);
});
