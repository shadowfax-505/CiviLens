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

it('draws only from the chosen calibration group, script included', function (): void {
    // Filtering by publisher alone was why nineteen labels per group was slow
    // work. A threshold is fitted per publisher x script, so judging
    // "cbad-bangladesh" drew across its bn, en and mixed pools at once — and
    // cbad|mixed holds 40 candidates against 5,986, so the group needing 19
    // labels would almost never be shown.
    ['page' => $page] = publishableDocument();

    $wanted = certifiedFieldOn($page, 'deferred', [
        'publisher_group' => 'cbad-bangladesh',
        'script_class' => 'mixed',
        'gold_source' => null,
        'is_correct' => null,
    ]);

    foreach (['bn', 'en'] as $script) {
        foreach (range(1, 15) as $ignored) {
            certifiedFieldOn($page, 'deferred', [
                'publisher_group' => 'cbad-bangladesh',
                'script_class' => $script,
                'gold_source' => null,
                'is_correct' => null,
            ]);
        }
    }

    $this->actingAs(searchAdminUser());

    // Thirty draws against one wanted item in thirty-one: under the old
    // publisher-only filter this would almost certainly serve something else.
    for ($attempt = 0; $attempt < 30; $attempt++) {
        $item = $this->get(route('admin.sources.review', ['group' => 'cbad-bangladesh|mixed']))
            ->assertOk()
            ->viewData('item');

        expect($item?->id)->toBe($wanted->id);
    }
});

it('still honours a publisher-only group left in an old session', function (): void {
    // A session predating the per-script filter should keep working rather than
    // silently serving the whole corpus.
    ['page' => $page] = publishableDocument();

    $wanted = certifiedFieldOn($page, 'deferred', [
        'publisher_group' => 'imed-bangladesh',
        'script_class' => 'bn',
        'gold_source' => null,
        'is_correct' => null,
    ]);

    certifiedFieldOn($page, 'deferred', [
        'publisher_group' => 'dgcivil-bangladesh',
        'script_class' => 'bn',
        'gold_source' => null,
        'is_correct' => null,
    ]);

    $this->actingAs(searchAdminUser());

    $item = $this->get(route('admin.sources.review', ['group' => 'imed-bangladesh']))
        ->assertOk()
        ->viewData('item');

    expect($item?->id)->toBe($wanted->id);
});

it('offers the group that can be finished soonest first', function (): void {
    // A reviewer opening this screen should see the group they can complete
    // soonest, not the one that happens to be largest. Sixteen candidates that
    // finish a group beat five thousand that finish nothing.
    ['page' => $page] = publishableDocument();

    foreach (range(1, 40) as $ignored) {
        certifiedFieldOn($page, 'deferred', [
            'publisher_group' => 'cbad-bangladesh',
            'script_class' => 'en',
            'gold_source' => null,
            'is_correct' => null,
        ]);
    }

    foreach (range(1, 5) as $ignored) {
        certifiedFieldOn($page, 'deferred', [
            'publisher_group' => 'parliament-bangladesh',
            'script_class' => 'bn',
            'gold_source' => null,
            'is_correct' => null,
        ]);
    }

    $this->actingAs(searchAdminUser());

    $groups = collect($this->get(route('admin.sources.review'))->viewData('groups'));

    // parliament|bn can be exhausted in five; cbad|en needs nineteen.
    expect($groups->first()['key'])->toBe('parliament-bangladesh|bn')
        ->and($groups->first()['remaining_effort'])->toBe(5);
});

it('sinks a group that has already reached the target', function (): void {
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

    certifiedFieldOn($page, 'deferred', [
        'publisher_group' => 'cbad-bangladesh',
        'script_class' => 'bn',
        'gold_source' => null,
        'is_correct' => null,
    ]);

    $this->actingAs(searchAdminUser());

    $groups = collect($this->get(route('admin.sources.review'))->viewData('groups'));

    expect($groups->first()['key'])->toBe('cbad-bangladesh|bn')
        ->and($groups->last()['key'])->toBe('dgcivil-bangladesh|bn');
});

it('shows what was judged beside what the calibration could use', function (): void {
    // A reviewer judged 624 values in one group and the screen showed 19, with
    // nothing to explain the other 605. Two things discount a judgement and
    // neither was visible: a share is held out to measure error rather than to
    // fit the threshold, and a value with no score cannot be in a conformal
    // calibration set at all. Almost every one of those is waiting on a
    // re-score, not on more judgements.
    ['page' => $page] = publishableDocument();

    foreach (range(1, 4) as $ignored) {
        certifiedFieldOn($page, 'accepted', [
            'publisher_group' => 'imed-bangladesh',
            'script_class' => 'en',
            'gold_source' => 'reviewer',
            'is_correct' => true,
            'calibration_split' => 'calibration',
            'nonconformity_score' => 0.1,
        ]);
    }

    foreach (range(1, 7) as $ignored) {
        certifiedFieldOn($page, 'deferred', [
            'publisher_group' => 'imed-bangladesh',
            'script_class' => 'en',
            'gold_source' => 'reviewer',
            'is_correct' => true,
            'calibration_split' => 'calibration',
            // Unlocatable by a locator that has since been fixed.
            'nonconformity_score' => null,
            'score_basis' => 'unlocatable',
        ]);
    }

    $this->actingAs(searchAdminUser());

    $group = collect($this->get(route('admin.sources.review'))->viewData('groups'))
        ->firstWhere('key', 'imed-bangladesh|en');

    expect($group['judged'])->toBe(11)
        ->and($group['size'])->toBe(4)
        ->and($group['unscorable'])->toBe(7);

    $this->get(route('admin.sources.review'))->assertSee('awaiting a re-score');
});

it('shows a group whose every judgement is unscorable and whose queue is empty', function (): void {
    // parliament-bangladesh|bn: fifteen values judged, none scorable, nothing
    // left to judge. It appeared in neither the labelled list nor the unjudged
    // list, so it appeared nowhere — a reviewer had no way to learn the group
    // existed, let alone that it is stuck waiting on a re-score. A screen that
    // omits a group certifying nothing is the same silent narrowing as a report
    // that omits a zero.
    ['page' => $page] = publishableDocument();

    foreach (range(1, 3) as $ignored) {
        certifiedFieldOn($page, 'deferred', [
            'publisher_group' => 'parliament-bangladesh',
            'script_class' => 'bn',
            'gold_source' => 'reviewer',
            'is_correct' => true,
            'calibration_split' => 'calibration',
            'nonconformity_score' => null,
            'score_basis' => 'unlocatable',
        ]);
    }

    $this->actingAs(searchAdminUser());

    $group = collect($this->get(route('admin.sources.review'))->viewData('groups'))
        ->firstWhere('key', 'parliament-bangladesh|bn');

    expect($group)->not->toBeNull()
        ->and($group['judged'])->toBe(3)
        ->and($group['size'])->toBe(0)
        ->and($group['unscorable'])->toBe(3)
        ->and($group['waiting'])->toBe(0);
});
