<?php

use App\Models\ExtractionField;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{wanted: ExtractionField}
 */
function crowdedQueue(): array
{
    ExtractionField::factory()->count(30)->create([
        'publisher_group' => 'dgcivil-bangladesh',
        'is_correct' => null,
        'gold_source' => null,
    ]);

    return ['wanted' => ExtractionField::factory()->create([
        'publisher_group' => 'cbad-bangladesh',
        'is_correct' => null,
        'gold_source' => null,
    ])];
}

it('draws only from the chosen calibration group', function (): void {
    // cbad-bangladesh holds 69 of 17,773 unlabelled candidates, so a draw over
    // the whole corpus needs about 5,000 judgements to turn up the 19 its own
    // calibration requires. The group-conditional scheme of ADR-018 exists for
    // publishers that rare, and the queue was why it could never be used on one.
    ['wanted' => $wanted] = crowdedQueue();

    $this->actingAs(searchAdminUser())
        ->get(route('admin.sources.review', ['group' => 'cbad-bangladesh']))
        ->assertOk()
        ->assertViewHas('item', fn (?ExtractionField $item): bool => $item?->getKey() === $wanted->getKey());
});

it('keeps the group across the redirect that follows a judgement', function (): void {
    // store() returns with back(), so the choice has to survive without the
    // form carrying it.
    ['wanted' => $wanted] = crowdedQueue();

    $admin = searchAdminUser();

    $this->actingAs($admin)->get(route('admin.sources.review', ['group' => 'cbad-bangladesh']))->assertOk();

    $this->actingAs($admin)
        ->get(route('admin.sources.review'))
        ->assertOk()
        ->assertViewHas('item', fn (?ExtractionField $item): bool => $item?->getKey() === $wanted->getKey());
});

it('returns to the whole corpus when the group is cleared', function (): void {
    crowdedQueue();

    $admin = searchAdminUser();

    $this->actingAs($admin)->get(route('admin.sources.review', ['group' => 'cbad-bangladesh']))->assertOk();

    $this->actingAs($admin)
        ->get(route('admin.sources.review', ['group' => '']))
        ->assertOk()
        ->assertViewHas('group', null);
});

it('still draws at random inside the group', function (): void {
    // The one thing narrowing must not become is ordering. Judging the easiest
    // items of a group first would make its calibration set unrepresentative of
    // what the system accepts from it, which is the failure the whole-corpus
    // draw was protecting against.
    ExtractionField::factory()->count(40)->create([
        'publisher_group' => 'cbad-bangladesh',
        'is_correct' => null,
        'gold_source' => null,
    ]);

    $admin = searchAdminUser();
    $seen = [];

    for ($draw = 0; $draw < 12; $draw++) {
        $response = $this->actingAs($admin)->get(route('admin.sources.review', ['group' => 'cbad-bangladesh']));
        $seen[] = $response->viewData('item')?->getKey();
    }

    expect(count(array_unique(array_filter($seen))))->toBeGreaterThan(1);
});

it('counts what is left to judge in each stratum, per script and kind', function (): void {
    // Keyed by publisher, script AND kind of value now, because that is the
    // unit a threshold is fitted on. Keyed by publisher alone, the chips
    // offered a selection the calibration could not use: judging
    // "cbad-bangladesh" drew across its bn, en and mixed pools at once, and the
    // group needing nineteen labels was whichever of the three the draw
    // happened to miss. The kind is the same argument again — a threshold
    // fitted on dates is not a claim about case numbers.
    crowdedQueue();

    $groups = collect(
        $this->actingAs(searchAdminUser())
            ->get(route('admin.sources.review'))
            ->assertOk()
            ->viewData('groups')
    )->keyBy('key');

    $cbad = ExtractionField::query()->where('publisher_group', 'cbad-bangladesh')->firstOrFail();

    expect($groups->get('cbad-bangladesh|'.$cbad->script_class.'|'.$cbad->field_key)['waiting'])->toBe(1)
        ->and($groups->sum('waiting'))->toBe(31);
});

it('serves a value somebody else has already judged, for a second opinion', function (): void {
    // The queue serves unjudged values, so a second reviewer would spend a
    // whole session never once seeing what the first had seen — and the
    // double-judged subset would never come into existence.
    $first = searchAdminUser();

    $judged = ExtractionField::factory()->create([
        'gold_source' => 'reviewer',
        'is_correct' => true,
        'reviewed_by' => $first->id,
    ]);

    ExtractionField::factory()->count(5)->create(['is_correct' => null, 'gold_source' => null]);

    $this->actingAs(searchAdminUser())
        ->get(route('admin.sources.review', ['second' => 1]))
        ->assertOk()
        ->assertViewHas('item', fn (?ExtractionField $item): bool => $item?->getKey() === $judged->getKey());
});

it('does not ask a reviewer to judge what they already judged', function (): void {
    $reviewer = searchAdminUser();

    ExtractionField::factory()->create([
        'gold_source' => 'reviewer',
        'is_correct' => true,
        'reviewed_by' => $reviewer->id,
    ]);

    $this->actingAs($reviewer)
        ->get(route('admin.sources.review', ['second' => 1]))
        ->assertOk()
        ->assertViewHas('item', null);
});
