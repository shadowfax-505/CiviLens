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
