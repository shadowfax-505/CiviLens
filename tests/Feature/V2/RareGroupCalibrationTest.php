<?php

use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Services\Extraction\CalibrationReport;
use App\Services\Extraction\ConformalCalibrator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @param  list<bool>  $outcomes  true where the reviewer judged the value correct
 */
function labelled(string $publisher, string $script, array $outcomes, string $split = 'calibration'): void
{
    $page = ExtractionPage::factory()->create(['script_class' => $script]);

    foreach ($outcomes as $index => $correct) {
        ExtractionField::query()->create([
            'extraction_run_id' => $page->extraction_run_id,
            'extraction_page_id' => $page->id,
            'field_key' => 'amount', 'field_type' => 'string',
            'extracted_value' => (string) (1000 + $index), 'script_class' => $script,
            'publisher_group' => $publisher, 'confidence' => 84.0,
            // Spread so a threshold can fall between them; wrong values score worse.
            'nonconformity_score' => $correct ? 0.10 + ($index / 1000) : 0.90 + ($index / 1000),
            'decision' => 'pending', 'is_correct' => $correct,
            'gold_value' => $correct ? (string) (1000 + $index) : null,
            'gold_source' => 'reviewer', 'calibration_split' => $split,
            'evidence_page_number' => 1, 'evidence_offset_start' => 0, 'evidence_offset_end' => 4,
        ]);
    }
}

it('states the tightest risk level a group of a given size can support', function (): void {
    // n >= 1/alpha - 1 rearranges to alpha >= 1/(n+1). Nineteen is not a wall,
    // it is the price of 0.05 in particular.
    $calibrator = app(ConformalCalibrator::class);

    expect($calibrator->attainableAlpha(19))->toBe(0.05)
        ->and($calibrator->attainableAlpha(9))->toBe(0.1)
        ->and($calibrator->attainableAlpha(4))->toBe(0.2)
        // And the two statements have to agree at the boundary, or one of them
        // is certifying a group the other says it cannot.
        ->and($calibrator->minimumCalibrationSize(0.05))->toBe(19)
        ->and($calibrator->minimumCalibrationSize(0.1))->toBe(9);
});

it('certifies a rare group at a looser level instead of certifying it for nothing', function (): void {
    // Nine labels cannot support 0.05. Deferring the whole publisher was the old
    // behaviour; reporting what nine labels do support is the honest one.
    labelled('rare-publisher', 'bn', array_fill(0, 9, true));

    $groups = app(ConformalCalibrator::class)->calibrateAdaptive(0.05);
    $rare = $groups->get('rare-publisher|bn');

    expect($rare->certifiable())->toBeTrue()
        ->and($rare->certifiedAlpha)->toBe(0.1)
        ->and($rare->attainableAlpha)->toBe(0.1)
        // The claim is still about this group, just a weaker claim.
        ->and($rare->conditional())->toBeTrue();
});

it('leaves a group that is big enough exactly as it was', function (): void {
    labelled('busy-publisher', 'bn', array_fill(0, 40, true));

    $group = app(ConformalCalibrator::class)->calibrateAdaptive(0.05)->get('busy-publisher|bn');

    expect($group->certifiedAlpha)->toBe(0.05)
        ->and($group->basis)->toBe('group')
        ->and($group->threshold)->not->toBeNull();
});

it('borrows a wider threshold for a group too small to say anything, and says so', function (): void {
    // Two labels support only alpha = 0.33, past any level worth reporting. The
    // script-wide threshold applies to the script, not to this publisher, and
    // the difference is recorded rather than glossed.
    labelled('busy-publisher', 'bn', array_fill(0, 40, true));
    labelled('brand-new-publisher', 'bn', [true, true]);

    $group = app(ConformalCalibrator::class)->calibrateAdaptive(0.05)->get('brand-new-publisher|bn');

    expect($group->threshold)->not->toBeNull()
        ->and($group->basis)->toBe('script')
        ->and($group->conditional())->toBeFalse()
        ->and($group->calibrationSize)->toBe(2);
});

it('certifies nothing when even the wider population is too small', function (): void {
    // Borrowing from a pool that cannot certify either would be inventing a
    // guarantee out of two populations that each lack one.
    labelled('brand-new-publisher', 'bn', [true, true]);

    $group = app(ConformalCalibrator::class)->calibrateAdaptive(0.05)->get('brand-new-publisher|bn');

    expect($group->threshold)->toBeNull()
        ->and($group->basis)->toBe('none');
});

it('reports which groups are certified conditionally and which borrowed', function (): void {
    labelled('busy-publisher', 'bn', array_fill(0, 40, true));
    labelled('brand-new-publisher', 'bn', [true, true]);
    labelled('rare-publisher', 'en', array_fill(0, 9, true));

    $report = app(CalibrationReport::class)->build(0.05);

    expect($report['groups'])->toHaveCount(3)
        ->and($report['borrowed_groups'])->toBe(['brand-new-publisher|bn'])
        ->and($report['relaxed_groups'])->toBe(['rare-publisher|en'])
        ->and($report['uncertifiable_groups'])->toBe([]);
});
