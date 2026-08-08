<?php

use App\Models\ExtractionField;
use App\Models\ExtractionRun;
use App\Services\Extraction\CalibrationReport;
use App\Services\Extraction\ConformalCalibrator;
use App\Services\Extraction\FieldDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @param  list<array{float, bool}>  $rows  score, isCorrect
 */
function seedFields(string $publisher, string $script, string $split, array $rows, ?ExtractionRun $run = null): ExtractionRun
{
    $run ??= ExtractionRun::factory()->create();

    foreach ($rows as [$score, $correct]) {
        ExtractionField::factory()->create([
            'extraction_run_id' => $run->id,
            'publisher_group' => $publisher,
            'script_class' => $script,
            'calibration_split' => $split,
            'nonconformity_score' => $score,
            'is_correct' => $correct,
        ]);
    }

    return $run;
}

/** @return list<array{float, bool}> */
function scoreSeries(int $count, float $start, float $step, float $wrongAbove): array
{
    $rows = [];

    for ($i = 0; $i < $count; $i++) {
        $score = round($start + ($i * $step), 6);
        $rows[] = [$score, $score < $wrongAbove];
    }

    return $rows;
}

it('requires a calibration set large enough for the finite-sample correction', function (): void {
    $calibrator = new ConformalCalibrator;

    expect($calibrator->minimumCalibrationSize(0.05))->toBe(19)
        ->and($calibrator->minimumCalibrationSize(0.1))->toBe(9)
        ->and($calibrator->minimumCalibrationSize(0.5))->toBe(1);
});

it('picks the largest threshold whose corrected risk still satisfies alpha', function (): void {
    // 19 clean fields then one wrong at 0.95. At alpha 0.10 and n=20 the bound is
    // (errors + 1) / 21 <= 0.10, so at most one error may be admitted.
    $rows = [];
    for ($i = 0; $i < 19; $i++) {
        $rows[] = [round(0.01 * ($i + 1), 6), true];
    }
    $rows[] = [0.95, false];
    seedFields('mof', 'bn', 'calibration', $rows);

    $calibration = (new ConformalCalibrator)->calibrate(0.10)->get('mof|bn');

    expect($calibration->calibrationSize)->toBe(20)
        ->and($calibration->certifiable())->toBeTrue()
        ->and($calibration->threshold)->toBe(0.95)
        ->and($calibration->empiricalFalseAcceptanceRate)->toBe(0.05);
});

it('refuses to certify a group that is too small to satisfy the bound', function (): void {
    seedFields('tiny', 'bn', 'calibration', [[0.01, true], [0.02, true], [0.03, true]]);

    $calibration = (new ConformalCalibrator)->calibrate(0.05)->get('tiny|bn');

    expect($calibration->calibrationSize)->toBe(3)
        ->and($calibration->minimumCalibrationSize)->toBe(19)
        ->and($calibration->certifiable())->toBeFalse()
        ->and($calibration->threshold)->toBeNull();
});

it('defers every field in an uncertifiable group instead of guessing', function (): void {
    seedFields('tiny', 'bn', 'calibration', [[0.01, true], [0.02, true]]);
    seedFields('tiny', 'bn', 'test', [[0.01, true], [0.02, true]]);

    $counts = app(FieldDecisionService::class)->decide(0.05);

    expect($counts[FieldDecisionService::ACCEPTED])->toBe(0)
        ->and($counts[FieldDecisionService::DEFERRED])->toBe(2)
        ->and(ExtractionField::query()->where('calibration_split', 'test')->pluck('decision')->unique()->all())
        ->toBe([FieldDecisionService::DEFERRED]);
});

it('defers a field that has no nonconformity score', function (): void {
    seedFields('mof', 'en', 'calibration', scoreSeries(40, 0.01, 0.01, 0.90));
    $run = ExtractionRun::factory()->create();
    ExtractionField::factory()->create([
        'extraction_run_id' => $run->id,
        'publisher_group' => 'mof',
        'script_class' => 'en',
        'calibration_split' => 'test',
        'nonconformity_score' => null,
        'is_correct' => null,
    ]);

    app(FieldDecisionService::class)->decide(0.05);

    expect(ExtractionField::query()->where('calibration_split', 'test')->sole()->decision)
        ->toBe(FieldDecisionService::DEFERRED);
});

it('holds the false acceptance guarantee on held-out data', function (): void {
    seedFields('mof', 'en', 'calibration', scoreSeries(200, 0.001, 0.005, 0.80));
    seedFields('mof', 'en', 'test', scoreSeries(200, 0.001, 0.005, 0.80));

    $report = app(CalibrationReport::class)->build(0.05);
    $group = collect($report['realized']['groups'])->firstWhere('group', 'mof|en');

    expect($group['group_conditional']['false_acceptance_rate'])->toBeLessThanOrEqual(0.05)
        ->and($group['group_conditional']['accepted'])->toBeGreaterThan(0);
});

it('shows a pooled threshold breaching alpha on the low-resource group it hides', function (): void {
    // Latin fields dominate and are reliable across the whole score range.
    seedFields('mof', 'en', 'calibration', scoreSeries(400, 0.001, 0.002, 0.95));
    seedFields('mof', 'en', 'test', scoreSeries(400, 0.001, 0.002, 0.95));

    // Bengali fields are a small minority and go wrong far earlier, so a
    // threshold fitted on the pooled population admits many of their errors.
    seedFields('mof', 'bn', 'calibration', scoreSeries(40, 0.001, 0.002, 0.02));
    seedFields('mof', 'bn', 'test', scoreSeries(40, 0.001, 0.002, 0.02));

    $report = app(CalibrationReport::class)->build(0.05);
    $bengali = collect($report['realized']['groups'])->firstWhere('group', 'mof|bn');

    expect($report['realized']['overall']['pooled']['false_acceptance_rate'])->toBeLessThanOrEqual(0.05)
        ->and($bengali['pooled']['false_acceptance_rate'])->toBeGreaterThan(0.05)
        ->and($bengali['pooled_breaches_alpha'])->toBeTrue()
        ->and($bengali['group_conditional']['false_acceptance_rate'])->toBeLessThanOrEqual(0.05)
        ->and($report['realized']['groups_where_pooled_breaches_alpha'])->toContain('mof|bn');
});

it('states what it guarantees and what it only measures', function (): void {
    seedFields('mof', 'en', 'calibration', scoreSeries(40, 0.01, 0.01, 0.90));

    $report = app(CalibrationReport::class)->build(0.05);

    expect($report['guaranteed_quantity'])->toContain('AND wrong')
        ->and($report['not_guaranteed'])->toContain('empirically')
        ->and($report['alpha'])->toBe(0.05)
        ->and($report['minimum_calibration_size'])->toBe(19);
});

it('flags a nonconformity score that already knows the answer', function (): void {
    // Correct fields score low, incorrect fields score high, with no overlap --
    // the signature of a score derived from the gold value rather than predicted.
    seedFields('leaky', 'bn', 'calibration', [
        [0.10, true], [0.20, true], [0.30, true],
        [0.90, false], [0.95, false], [1.00, false],
    ]);

    $leakage = app(CalibrationReport::class)->build(0.05)['label_leakage'];

    expect($leakage['suspected'])->toBeTrue()
        ->and($leakage['incorrect_scoring_below_worst_correct'])->toBe(0)
        ->and($leakage['reason'])->toContain('vacuous');
});

it('does not flag leakage when outcomes overlap in score', function (): void {
    seedFields('honest', 'bn', 'calibration', [
        [0.10, true], [0.20, false], [0.30, true], [0.40, false], [0.50, true],
    ]);

    $leakage = app(CalibrationReport::class)->build(0.05)['label_leakage'];

    expect($leakage['suspected'])->toBeFalse()
        ->and($leakage['reason'])->toBeNull()
        ->and($leakage['incorrect_scoring_below_worst_correct'])->toBeGreaterThan(0);
});
