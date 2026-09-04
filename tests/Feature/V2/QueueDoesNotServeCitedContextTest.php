<?php

use App\Models\ExtractionField;
use App\Services\Extraction\ReviewCandidateGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function judgeableValue(string $kind, string $value): ExtractionField
{
    ['page' => $page] = publishableDocument();

    return ExtractionField::factory()->create([
        'extraction_run_id' => $page->extraction_run_id,
        'extraction_page_id' => $page->id,
        'field_key' => $kind,
        'extracted_value' => $value,
        'publisher_group' => 'supremecourt-bangladesh',
        'script_class' => 'en',
        'nonconformity_score' => $kind === ReviewCandidateGenerator::CITED_CONTEXT_KIND ? null : 0.01,
        'gold_source' => null,
        'is_correct' => null,
    ]);
}

it('never serves a value that can never be certified', function (): void {
    // A finding span carries no score and cannot acquire one — a 17-word floor
    // keeps it past anything the locator can span — so no number of judgements
    // will certify one. 3,067 sat in the unlabelled pool, and a reviewer's
    // attention is the scarcest thing this project spends.
    foreach (range(1, 12) as $index) {
        judgeableValue(
            ReviewCandidateGenerator::CITED_CONTEXT_KIND,
            'we hold that the impugned order number '.$index.' cannot be sustained in law at all',
        );
    }

    judgeableValue('disposition', 'the Rule is made absolute');

    // Drawn at random, so every draw must land on the one judgeable value.
    foreach (range(1, 6) as $ignored) {
        $this->actingAs(searchAdminUser())
            ->get(route('admin.sources.review'))
            ->assertOk()
            ->assertSee('the Rule is made absolute', false)
            ->assertDontSee('cannot be sustained in law', false);
    }
});

it('offers no stratum a reviewer could never finish', function (): void {
    // Worse than the wasted draws was the progress table. It keys a row per
    // publisher, script and kind, so the cited-context stratum appeared with
    // "19 more" beside it, inviting someone to finish a stratum that cannot
    // exist.
    judgeableValue(ReviewCandidateGenerator::CITED_CONTEXT_KIND, 'we find that the appeal must fail on the merits alone');
    judgeableValue('disposition', 'the appeal is dismissed');

    $levels = collect(
        $this->actingAs(searchAdminUser())
            ->get(route('admin.sources.review'))
            ->assertOk()
            ->viewData('groups')
    )->pluck('key');

    expect($levels)->toContain('supremecourt-bangladesh|en|disposition')
        ->and($levels)->not->toContain('supremecourt-bangladesh|en|'.ReviewCandidateGenerator::CITED_CONTEXT_KIND);
});

it('does not count cited context toward what is left to judge', function (): void {
    judgeableValue(ReviewCandidateGenerator::CITED_CONTEXT_KIND, 'we are of the view that the order below cannot stand at all');
    judgeableValue('disposition', 'the Rule is discharged');

    $waiting = collect(
        $this->actingAs(searchAdminUser())
            ->get(route('admin.sources.review'))
            ->assertOk()
            ->viewData('groups')
    )->sum('waiting');

    expect($waiting)->toBe(1);
});
