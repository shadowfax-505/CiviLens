<?php

use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Models\Statute;
use App\Models\StatutoryCitationResolution;
use App\Services\Statutes\CitationResolver;
use App\Services\Statutes\ParsedCitation;
use App\Services\Statutes\StatutoryCitationParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/** The Code of Civil Procedure, with the one provision this corpus cites most. */
function codeOfCivilProcedure(): Statute
{
    $statute = Statute::query()->create([
        'instrument_key' => 'code-of-civil-procedure',
        'title' => 'The Code of Civil Procedure, 1908',
        'instrument_year' => 1908,
        'known_as' => ['cpc', 'c-p-c'],
    ]);

    $statute->provisions()->create([
        'provision_type' => 'section',
        'provision_number' => '115',
        'provision_label' => '115(1)',
        'provision_key' => 'section-115-1',
        'heading' => 'Revision',
        'text' => 'The High Court Division may call for the record of any case which has been decided.',
        'position' => 115,
    ]);

    return $statute;
}

function citedFieldOn(ExtractionPage $page, string $value): ExtractionField
{
    return ExtractionField::factory()->create([
        'extraction_run_id' => $page->extraction_run_id,
        'extraction_page_id' => $page->id,
        'field_key' => 'statutory_reference',
        'field_type' => 'string',
        'extracted_value' => $value,
        'publisher_group' => 'supremecourt-bangladesh',
        'script_class' => 'en',
        'decision' => 'pending',
    ]);
}

it('matches a citation to the provision text held for it', function (): void {
    codeOfCivilProcedure();
    ['page' => $page] = publishableDocument();
    $field = citedFieldOn($page, 'Section 115(1) of the Code of Civil Procedure');

    $resolution = app(CitationResolver::class)->resolveField($field);

    expect($resolution->match_status)->toBe(StatutoryCitationResolution::STATUS_PROVISION)
        ->and($resolution->match_key)->toBe('code-of-civil-procedure/section-115-1')
        ->and($resolution->provision?->heading)->toBe('Revision')
        ->and($resolution->provision?->text)->toContain('call for the record')
        ->and($resolution->parser_version)->toBe(StatutoryCitationParser::VERSION)
        ->and($resolution->raw_value)->toBe('Section 115(1) of the Code of Civil Procedure');
});

it('reaches an instrument through a key it is known as', function (): void {
    // The parser keys "of the CPC" on 'cpc' and expands nothing, so the
    // expansion has to be a fact on the statute row. 26 values in the corpus
    // cite the Code that way and 27 cite a different Code as 'crpc', which is
    // exactly why this judgement belongs somewhere a person can see it.
    codeOfCivilProcedure();
    ['page' => $page] = publishableDocument();

    $resolution = app(CitationResolver::class)->resolveField(
        citedFieldOn($page, 'Section 115(1) of the CPC'),
    );

    expect($resolution->instrument_key)->toBe('cpc')
        ->and($resolution->match_status)->toBe(StatutoryCitationResolution::STATUS_PROVISION)
        ->and($resolution->statute?->instrument_key)->toBe('code-of-civil-procedure');
});

it('separates an instrument it holds from a provision it does not', function (): void {
    // "We hold this Code and not this section of it" is the answer that says
    // what to go and fetch. Folded into "no match" it would be indistinguishable
    // from never having held the Code at all.
    codeOfCivilProcedure();
    ['page' => $page] = publishableDocument();

    $held = app(CitationResolver::class)->resolveField(
        citedFieldOn($page, 'Section 151 of the Code of Civil Procedure'),
    );

    expect($held->match_status)->toBe(StatutoryCitationResolution::STATUS_INSTRUMENT)
        ->and($held->statute_id)->not->toBeNull()
        ->and($held->statute_provision_id)->toBeNull()
        ->and($held->matched_at)->not->toBeNull();

    $absent = app(CitationResolver::class)->resolveField(
        citedFieldOn($page, 'Section 420 of the Penal Code'),
    );

    expect($absent->match_status)->toBe(StatutoryCitationResolution::STATUS_UNMATCHED)
        ->and($absent->match_key)->toBe('penal-code/section-420')
        ->and($absent->statute_id)->toBeNull()
        ->and($absent->matched_at)->toBeNull();
});

it('records a citation it could not parse rather than dropping it', function (): void {
    // 912 of the corpus values cite "the Code" and there are two of those in the
    // same documents. A row saying so is what keeps that a countable fact
    // instead of a silent gap between 7,874 and whatever parsed.
    ['page' => $page] = publishableDocument();

    $resolution = app(CitationResolver::class)->resolveField(
        citedFieldOn($page, 'Section 151 of the Code'),
    );

    expect($resolution->match_status)->toBe(StatutoryCitationResolution::STATUS_UNPARSED)
        ->and($resolution->reject_reason)->toBe(ParsedCitation::REASON_ANAPHORIC_INSTRUMENT)
        ->and($resolution->match_key)->toBeNull()
        ->and($resolution->instrument_key)->toBeNull()
        // The provision was read even though the instrument was not, and that is
        // the half of the citation worth keeping.
        ->and($resolution->provision_number)->toBe('151')
        ->and($resolution->provision_type)->toBe('section');
});

it('replaces an earlier parse of the same value instead of accumulating them', function (): void {
    // A parse is a pure function of the string and the parser version, so a
    // second row for the same pair would record nothing a re-run could not
    // reproduce. Re-parsing 7,874 values must not multiply the table.
    codeOfCivilProcedure();
    ['page' => $page] = publishableDocument();
    $field = citedFieldOn($page, 'Section 115(1) of the Code of Civil Procedure');

    app(CitationResolver::class)->resolveField($field);
    app(CitationResolver::class)->resolveField($field);

    expect(StatutoryCitationResolution::query()->where('extraction_field_id', $field->getKey())->count())->toBe(1);
});

it('counts every value of the kind it was asked for and no other', function (): void {
    codeOfCivilProcedure();
    ['page' => $page] = publishableDocument();

    citedFieldOn($page, 'Section 115(1) of the Code of Civil Procedure');
    citedFieldOn($page, 'Section 151 of the Code of Civil Procedure');
    citedFieldOn($page, 'Section 420 of the Penal Code');
    citedFieldOn($page, 'Section 151 of the Code');

    // A date on the same page, which this layer has nothing to say about.
    ExtractionField::factory()->create([
        'extraction_run_id' => $page->extraction_run_id,
        'extraction_page_id' => $page->id,
        'field_key' => 'date',
        'extracted_value' => '12 March 2024',
    ]);

    $counts = app(CitationResolver::class)->resolveKind('statutory_reference', chunk: 2);

    expect($counts['read'])->toBe(4)
        ->and($counts['provision'])->toBe(1)
        ->and($counts['instrument'])->toBe(1)
        ->and($counts['unmatched'])->toBe(1)
        ->and($counts['unparsed'])->toBe(1)
        ->and($counts['reasons'])->toBe([ParsedCitation::REASON_ANAPHORIC_INSTRUMENT => 1])
        ->and(StatutoryCitationResolution::query()->count())->toBe(4);
});

it('reports what a kind would resolve to without writing a row', function (): void {
    // The runbook's rule for anything that changes stored data: report first,
    // act only with --apply. An operator should see the distribution before
    // 7,874 rows are written on the strength of it.
    codeOfCivilProcedure();
    ['page' => $page] = publishableDocument();

    citedFieldOn($page, 'Section 115(1) of the Code of Civil Procedure');
    citedFieldOn($page, 'Section 420 of the Penal Code');
    citedFieldOn($page, 'Section 151 of the Code');

    $counts = app(CitationResolver::class)->previewKind('statutory_reference', chunk: 2);

    expect($counts['read'])->toBe(3)
        ->and($counts['provision'])->toBe(1)
        ->and($counts['unmatched'])->toBe(1)
        ->and($counts['unparsed'])->toBe(1)
        ->and($counts['reasons'])->toBe([ParsedCitation::REASON_ANAPHORIC_INSTRUMENT => 1])
        ->and(StatutoryCitationResolution::query()->count())->toBe(0);
});

it('resolves a citation without certifying it', function (): void {
    // ADR-024: the stratum `supremecourt-bangladesh | en | statutory_reference`
    // holds 7,780 values and has been judged zero times, so no threshold has
    // ever been fitted for it and every one of these is a candidate. A match
    // between two strings is not a bound, and the way that stays true is
    // structural: this layer writes its own table and there is no column on it
    // that could hold a level.
    codeOfCivilProcedure();
    ['page' => $page] = publishableDocument();
    $field = citedFieldOn($page, 'Section 115(1) of the Code of Civil Procedure');

    $resolution = app(CitationResolver::class)->resolveField($field);

    expect($resolution->match_status)->toBe(StatutoryCitationResolution::STATUS_PROVISION)
        ->and($field->fresh()?->decision)->toBe('pending')
        ->and($field->fresh()?->decision_alpha)->toBeNull()
        ->and($field->fresh()?->decision_basis)->toBeNull()
        ->and(ExtractionField::query()->where('decision', 'accepted')->count())->toBe(0);

    $columns = Schema::getColumnListing('statutory_citation_resolutions');

    foreach (['decision', 'decision_alpha', 'decision_basis', 'certified_at', 'alpha', 'nonconformity_score'] as $forbidden) {
        expect($columns)->not->toContain($forbidden);
    }
});
