<?php

use App\Data\Extraction\GroupCalibration;
use App\Models\ExtractionField;
use App\Services\Extraction\ConformalCalibrator;
use App\Services\Extraction\FieldDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** A group calibrated on `date` alone, with plenty of labels. */
function calibratedOnDatesAlone(int $labels = 40): void
{
    ['page' => $page] = publishableDocument();

    foreach (range(1, $labels) as $index) {
        ExtractionField::factory()->create([
            'extraction_run_id' => $page->extraction_run_id,
            'extraction_page_id' => $page->id,
            'field_key' => 'date',
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

function undecidedValue(string $kind): ExtractionField
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
    calibratedOnDatesAlone();
    $date = undecidedValue('date');

    app(FieldDecisionService::class)->decide(0.05);

    expect($date->refresh()->decision)->toBe(FieldDecisionService::ACCEPTED);
});

it('defers a kind no label in the group covers', function (): void {
    // The threshold rests on 105 dates. A case number is not a harder date: it
    // is a population the calibration set says nothing about, and a score below
    // a threshold fitted on something else is not evidence about it.
    calibratedOnDatesAlone();
    $caseNumber = undecidedValue('case_number');

    app(FieldDecisionService::class)->decide(0.05);

    expect($caseNumber->refresh()->decision)->toBe(FieldDecisionService::DEFERRED);
});

it('records which kinds a threshold rests on', function (): void {
    calibratedOnDatesAlone();

    $calibration = app(ConformalCalibrator::class)
        ->calibrateAdaptive(0.05)
        ->get('supremecourt-bangladesh|en');

    expect($calibration)->toBeInstanceOf(GroupCalibration::class)
        ->and($calibration->calibratedKinds)->toBe(['date'])
        ->and($calibration->covers('date'))->toBeTrue()
        ->and($calibration->covers('statutory_reference'))->toBeFalse();
});

it('covers everything when no kinds were recorded', function (): void {
    // An empty list means nothing was recorded, not that nothing was labelled.
    $blank = new GroupCalibration('x', 'en', 0.05, 40, 19, 0.5, 0.0, 1.0, 0.0);

    expect($blank->covers('anything'))->toBeTrue();
});
