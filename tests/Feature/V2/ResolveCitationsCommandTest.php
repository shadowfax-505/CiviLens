<?php

use App\Models\ExtractionField;
use App\Models\StatutoryCitationResolution;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function citationCandidate(string $value): ExtractionField
{
    ['page' => $page] = publishableDocument();

    return ExtractionField::factory()->create([
        'extraction_run_id' => $page->extraction_run_id,
        'extraction_page_id' => $page->id,
        'field_key' => 'statutory_reference',
        'extracted_value' => $value,
        'decision' => 'deferred',
    ]);
}

it('reports what the corpus parses without writing anything', function (): void {
    // Reports by default, as reroute-pages does. It matters more here: 84% of
    // these citations parse and none can be matched yet, because no statute
    // text has been fetched — that needs a publisher registered with a recorded
    // robots and terms decision, which is a separate step.
    citationCandidate('Section 410 of the Code of Criminal Procedure');
    citationCandidate('the Rule under the Code');

    $this->artisan('civiclens:resolve-citations')
        ->expectsOutputToContain('Nothing written')
        ->assertExitCode(0);

    expect(StatutoryCitationResolution::query()->count())->toBe(0);
});

it('writes the resolutions when asked', function (): void {
    citationCandidate('Section 410 of the Code of Criminal Procedure');

    $this->artisan('civiclens:resolve-citations', ['--apply' => true])
        ->expectsOutputToContain('None of them certifies anything')
        ->assertExitCode(0);

    expect(StatutoryCitationResolution::query()->count())->toBe(1);
});

it('certifies nothing it resolves', function (): void {
    // A resolution is a match, never a guarantee. The resolution table has no
    // decision, alpha or basis column at all, and the field it points at keeps
    // whatever decision the conformal pass gave it.
    $field = citationCandidate('Section 410 of the Code of Criminal Procedure');

    $this->artisan('civiclens:resolve-citations', ['--apply' => true])->assertExitCode(0);

    expect($field->refresh()->decision)->toBe('deferred')
        ->and($field->refresh()->decision_alpha)->toBeNull()
        ->and(StatutoryCitationResolution::query()->first()->getAttributes())
        ->not->toHaveKeys(['decision', 'decision_alpha', 'decision_basis']);
});

it('leaves other kinds of value alone', function (): void {
    citationCandidate('Section 410 of the Code of Criminal Procedure');

    ['page' => $page] = publishableDocument();
    ExtractionField::factory()->create([
        'extraction_run_id' => $page->extraction_run_id,
        'extraction_page_id' => $page->id,
        'field_key' => 'amount',
        'extracted_value' => '৩,৪৫,৬৭৮',
    ]);

    $this->artisan('civiclens:resolve-citations', ['--apply' => true])->assertExitCode(0);

    expect(StatutoryCitationResolution::query()->count())->toBe(1);
});
