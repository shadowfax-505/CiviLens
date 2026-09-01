<?php

use App\Models\ExtractionField;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('offers a certified value that was read poorly, and only that', function (): void {
    // 2,394 published figures were read at a token confidence below 80, and the
    // whole labelled evidence for that stratum is seven values, none wrong —
    // an interval running to 41%. That is not reassurance, it is an absence of
    // measurement, sitting exactly where the guarantee is least tested.
    ['page' => $page] = publishableDocument();

    $poorlyRead = certifiedFieldOn($page, 'accepted', [
        'token_confidence' => 57.2,
        'is_correct' => null,
        'gold_source' => null,
    ]);

    // Read well, and certified: not what this mode is for.
    certifiedFieldOn($page, 'accepted', [
        'token_confidence' => 96.0,
        'is_correct' => null,
        'gold_source' => null,
    ]);

    // Read poorly, and never certified: the bound says nothing about it.
    certifiedFieldOn($page, 'deferred', [
        'token_confidence' => 41.0,
        'is_correct' => null,
        'gold_source' => null,
    ]);

    $this->actingAs(searchAdminUser());

    for ($attempt = 0; $attempt < 12; $attempt++) {
        $item = $this->get(route('admin.sources.review', ['audit' => 1]))->assertOk()->viewData('item');

        expect($item?->id)->toBe($poorlyRead->id);
    }
});

it('keeps an audit judgement out of the calibration set and the held-out sample', function (): void {
    // A targeted draw cannot stand in for a uniform one. Both the calibrator
    // and the realized-rate report filter on 'calibration' and 'test' by name,
    // so a third name keeps these out of the bound entirely while still
    // measuring the stratum they were drawn from.
    ['page' => $page] = publishableDocument();

    $field = certifiedFieldOn($page, 'accepted', [
        'token_confidence' => 60.0,
        'is_correct' => null,
        'gold_source' => null,
    ]);

    $this->actingAs(searchAdminUser())
        ->post(route('admin.sources.review.store', $field), ['verdict' => 'correct', 'audit' => '1'])
        ->assertRedirect();

    expect($field->fresh()->calibration_split)->toBe('audit')
        ->and($field->fresh()->is_correct)->toBeTrue();
});

it('splits an ordinary judgement between calibration and the held-out sample as before', function (): void {
    ['page' => $page] = publishableDocument();

    $field = certifiedFieldOn($page, 'deferred', ['is_correct' => null, 'gold_source' => null]);

    $this->actingAs(searchAdminUser())
        ->post(route('admin.sources.review.store', $field), ['verdict' => 'correct'])
        ->assertRedirect();

    expect($field->fresh()->calibration_split)->toBeIn(['calibration', 'test']);
});

it('never lets an audit judgement reach the calibrator', function (): void {
    ['page' => $page] = publishableDocument();

    foreach (range(1, 5) as $ignored) {
        certifiedFieldOn($page, 'accepted', [
            'is_correct' => true,
            'gold_source' => 'reviewer',
            'calibration_split' => 'audit',
            'nonconformity_score' => 0.9,
        ]);
    }

    $counted = ExtractionField::query()
        ->calibratable()
        ->where('calibration_split', 'calibration')
        ->count();

    expect($counted)->toBe(0);
});
