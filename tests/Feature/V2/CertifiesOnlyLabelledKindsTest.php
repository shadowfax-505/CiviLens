<?php

use App\Models\ExtractionField;
use App\Services\Extraction\ConformalCalibrator;
use App\Services\Extraction\FieldDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** A stratum with plenty of labels, all of one kind. */
function strataLabelledOn(string $kind, int $labels = 40): void
{
    ['page' => $page] = publishableDocument();

    foreach (range(1, $labels) as $ignored) {
        ExtractionField::factory()->create([
            'extraction_run_id' => $page->extraction_run_id,
            'extraction_page_id' => $page->id,
            'field_key' => $kind,
            'publisher_group' => 'supremecourt-bangladesh',
            'script_class' => 'en',
            'calibration_split' => 'calibration',
            'nonconformity_score' => 0.01,
            'is_correct' => true,
            'gold_value' => 'seen',
            'gold_source' => 'reviewer',
        ]);
    }
}

function strataUndecided(string $kind): ExtractionField
{
    ['page' => $page] = publishableDocument();

    return ExtractionField::factory()->create([
        'extraction_run_id' => $page->extraction_run_id,
        'extraction_page_id' => $page->id,
        'field_key' => $kind,
        'publisher_group' => 'supremecourt-bangladesh',
        'script_class' => 'en',
        'calibration_split' => null,
        'nonconformity_score' => 0.01,
        'is_correct' => null,
        'gold_source' => null,
        'decision' => FieldDecisionService::DEFERRED,
    ]);
}

it('certifies a kind the calibration set contains', function (): void {
    strataLabelledOn('date');

    $date = strataUndecided('date');

    app(FieldDecisionService::class)->decide(0.05);

    expect($date->refresh()->decision)->toBe(FieldDecisionService::ACCEPTED)
        ->and($date->refresh()->decision_basis)->toBe('stratum');
});

it('defers a kind the group has judged nothing of', function (): void {
    // The stratum is publisher x script x kind, so a case number in a group
    // that has only ever labelled dates has no calibration of its own — and
    // deny-by-default takes it from there. A score below a threshold fitted on
    // something else is not evidence about it.
    strataLabelledOn('date');

    $caseNumber = strataUndecided('case_number');

    app(FieldDecisionService::class)->decide(0.05);

    expect($caseNumber->refresh()->decision)->toBe(FieldDecisionService::DEFERRED);
});

it('keys a calibration on publisher, script and kind', function (): void {
    strataLabelledOn('date');

    $calibrations = app(ConformalCalibrator::class)->calibrateAdaptive(0.05);

    expect($calibrations->keys()->all())->toBe(['supremecourt-bangladesh|en|date']);

    $stratum = $calibrations->get('supremecourt-bangladesh|en|date');

    expect($stratum->fieldKey)->toBe('date')
        ->and($stratum->groupKey())->toBe('supremecourt-bangladesh|en')
        ->and($stratum->basis)->toBe('stratum')
        ->and($stratum->conditional())->toBeTrue();
});

it('borrows the group threshold for a kind too thin to fit its own', function (): void {
    // Nineteen labels of dates and two of case numbers: the case-number stratum
    // cannot certify at 0.05 alone, so it borrows the threshold fitted across
    // every kind this publisher writes in this script — and says so, rather
    // than presenting a pooled threshold as a claim about case numbers.
    strataLabelledOn('date', 40);
    strataLabelledOn('case_number', 2);

    $stratum = app(ConformalCalibrator::class)
        ->calibrateAdaptive(0.05)
        ->get('supremecourt-bangladesh|en|case_number');

    expect($stratum->basis)->toBe('group')
        ->and($stratum->conditional())->toBeFalse()
        ->and($stratum->threshold)->not->toBeNull();
});
