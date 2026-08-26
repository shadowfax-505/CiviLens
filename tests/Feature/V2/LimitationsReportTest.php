<?php

use App\Models\ExtractionField;
use App\Models\ExtractionFieldJudgement;
use App\Services\Extraction\LimitationsReport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return list<string> */
function limitationsNow(): array
{
    return array_map(
        fn (array $row): string => $row['limitation'],
        app(LimitationsReport::class)->build(),
    );
}

it('reports that agreement between reviewers has never been measured', function (): void {
    // The bound is computed from human labels and is only as trustworthy as
    // those labels are reproducible. The machinery exists; the measurement
    // needs a second reviewer.
    expect(limitationsNow())
        ->toContain('Nobody has checked whether two people would judge these values the same way.');
});

it('stops reporting that once two people have judged the same value', function (): void {
    // A limitations section transcribed by hand goes stale in exactly the
    // direction that flatters the work. This one is measured, so it withdraws
    // itself when it stops being true.
    $field = ExtractionField::factory()->create(['gold_source' => 'reviewer']);

    foreach ([searchAdminUser(), searchAdminUser()] as $person) {
        ExtractionFieldJudgement::query()->create([
            'extraction_field_id' => $field->id,
            'user_id' => $person->id,
            'verdict' => 'correct',
            'value_judged' => (string) $field->extracted_value,
        ]);
    }

    expect(limitationsNow())
        ->not->toContain('Nobody has checked whether two people would judge these values the same way.');
});

it('names the publishers whose values are certified', function (): void {
    ExtractionField::factory()->count(3)->create([
        'decision' => 'accepted',
        'publisher_group' => 'dgcivil-bangladesh',
    ]);

    $single = collect(app(LimitationsReport::class)->build())
        ->firstWhere('limitation', 'Every certified value comes from one publisher.');

    expect($single['evidence']['certified_by_publisher'])->toBe(['dgcivil-bangladesh' => 3]);
});

it('says a share of values cannot be found again on their page', function (): void {
    // Such a value is never accepted, so it cannot breach the bound — which is
    // exactly why it is easy to miss. Twice this week a mis-cropped population
    // simply abstained and the guarantee stayed true over a narrower set.
    ExtractionField::factory()->count(3)->create(['score_basis' => 'second-read']);
    ExtractionField::factory()->create(['score_basis' => 'unlocatable']);

    $row = collect(app(LimitationsReport::class)->build())
        ->firstWhere('limitation', 'A share of values cannot be found again on their own page, and defer by rule.');

    expect($row['evidence']['unlocatable'])->toBe(1)
        ->and($row['evidence']['share'])->toBe(0.25);
});

it('counts values deferred because the two readings disputed the sign', function (): void {
    ExtractionField::factory()->count(2)->create(['score_basis' => 'sign-disagreement']);

    $row = collect(app(LimitationsReport::class)->build())
        ->firstWhere('limitation', 'The second read can agree with the first about something they are both wrong about.');

    expect($row['evidence']['values_deferred_for_a_disputed_sign'])->toBe(2);
});

it('says nothing about script names when no page carries legacy-font residue', function (): void {
    // The report withdraws a limitation the corpus does not have, or every
    // limitation becomes furniture a reader learns to skip.
    ['page' => $page] = publishableDocument();
    $page->forceFill(['extracted_text' => str_repeat('An ordinary English sentence. ', 20)])->save();
    certifiedFieldOn($page, 'accepted', ['script_class' => 'en']);

    expect(limitationsNow())
        ->not->toContain('The script classes are named after languages and one of them is not a language.');
});

it('reports that a script class named for a language holds none of it', function (): void {
    // Bengali set in a pre-Unicode font stores its characters as Latin bytes,
    // so the classifier reads the page as English. The router finds nothing to
    // reroute — the text layer is dense and only its alphabet is wrong — so
    // this is only visible by measuring the residue.
    ['page' => $page] = publishableDocument();
    $page->forceFill([
        'extracted_text' => str_repeat('ciivóª gš¿Yvjq wkí gš¿Yvjq AvBb I wePvi wefvM ', 12),
    ])->save();
    certifiedFieldOn($page, 'accepted', ['script_class' => 'en', 'is_correct' => true]);

    $report = collect(app(LimitationsReport::class)->build())
        ->firstWhere('limitation', 'The script classes are named after languages and one of them is not a language.');

    expect($report)->not->toBeNull()
        ->and($report['evidence']['pages_with_heavy_legacy_font_residue'])->toBe(1)
        // Where every accepted value and every label sit on the same
        // population, the bound is sound and only the name is wrong. Saying
        // which of the two it is, is the whole point of reporting the split.
        ->and($report['evidence']['by_script_class']['en']['accepted'])->toBe(1)
        ->and($report['evidence']['by_script_class']['en']['accepted_on_legacy_font_pages'])->toBe(1)
        ->and($report['evidence']['by_script_class']['en']['labels_on_legacy_font_pages'])->toBe(1)
        ->and($report['evidence']['by_script_class']['bn']['accepted_on_legacy_font_pages'])->toBe(0);
});
