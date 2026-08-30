<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists a group that certifies nothing, because that is the one worth judging', function (): void {
    // The table titled "what each group can certify as it stands" listed only
    // groups that already had labels — so the groups certifying nothing, which
    // are exactly the ones where nineteen judgements unlock thousands of
    // values, were the ones a reviewer could not see. A summary that omits
    // every zero describes the work already done rather than the work
    // available.
    ['page' => $page] = publishableDocument();

    certifiedFieldOn($page, 'deferred', [
        'publisher_group' => 'cbad-bangladesh',
        'script_class' => 'bn',
        'gold_source' => null,
        'is_correct' => null,
    ]);

    $this->actingAs(searchAdminUser());

    $response = $this->get(route('admin.sources.review'));
    $response->assertOk();

    $levels = collect($response->viewData('progress')['levels']);
    $group = $levels->firstWhere('key', 'cbad-bangladesh|bn');

    expect($group)->not->toBeNull()
        ->and($group['size'])->toBe(0)
        ->and($group['waiting'])->toBe(1)
        // Nineteen is ceil(1/0.05) - 1: the finite-sample minimum at the target.
        ->and($group['to_target'])->toBe(19);

    $response->assertSee('cbad-bangladesh|bn');
});

it('counts down to the target as labels arrive', function (): void {
    ['page' => $page] = publishableDocument();

    foreach (range(1, 3) as $ignored) {
        certifiedFieldOn($page, 'accepted', [
            'publisher_group' => 'cbad-bangladesh',
            'script_class' => 'bn',
            'gold_source' => 'reviewer',
            'is_correct' => true,
            'calibration_split' => 'calibration',
        ]);
    }

    $this->actingAs(searchAdminUser());

    $levels = collect($this->get(route('admin.sources.review'))->viewData('progress')['levels']);
    $group = $levels->firstWhere('key', 'cbad-bangladesh|bn');

    expect($group['size'])->toBe(3)
        ->and($group['to_target'])->toBe(16);
});

it('stops counting down once the target is reached', function (): void {
    ['page' => $page] = publishableDocument();

    foreach (range(1, 25) as $ignored) {
        certifiedFieldOn($page, 'accepted', [
            'publisher_group' => 'dgcivil-bangladesh',
            'script_class' => 'bn',
            'gold_source' => 'reviewer',
            'is_correct' => true,
            'calibration_split' => 'calibration',
        ]);
    }

    $this->actingAs(searchAdminUser());

    $levels = collect($this->get(route('admin.sources.review'))->viewData('progress')['levels']);

    expect($levels->firstWhere('key', 'dgcivil-bangladesh|bn')['to_target'])->toBe(0);
});

it('does not offer a finish line a group cannot reach', function (): void {
    // One group holds sixteen candidates against a target that needs nineteen.
    // Telling a reviewer "19 more" points at a line that does not exist;
    // judging every value it has still lands at a looser level, and a looser
    // level is a real level rather than nothing.
    ['page' => $page] = publishableDocument();

    foreach (range(1, 4) as $ignored) {
        certifiedFieldOn($page, 'deferred', [
            'publisher_group' => 'parliament-bangladesh',
            'script_class' => 'bn',
            'gold_source' => null,
            'is_correct' => null,
        ]);
    }

    $this->actingAs(searchAdminUser());

    $levels = collect($this->get(route('admin.sources.review'))->viewData('progress')['levels']);
    $group = $levels->firstWhere('key', 'parliament-bangladesh|bn');

    expect($group['waiting'])->toBe(4)
        ->and($group['to_target'])->toBe(19)
        // Four labels and nothing else: 1/(4+1) = 0.2.
        ->and($group['best_possible_alpha'])->toBe(0.2);

    $this->get(route('admin.sources.review'))->assertSee('out of reach');
});
