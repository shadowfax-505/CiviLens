<?php

use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Services\Extraction\ScoreDiscriminationReport;
use App\Services\Extraction\SecondReadScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** A page carrying one word, drawn so the recognizer has something to read. */
function scorablePage(string $word): ExtractionPage
{
    return ExtractionPage::factory()->create([
        'script_class' => 'en',
        'extraction_path' => 'ocr_primary',
        'recognized_dpi' => 150,
        'recognized_words' => [['t' => $word, 'c' => 92.0, 'l' => 40, 'y' => 40, 'w' => 200, 'h' => 40]],
    ]);
}

function scorableField(ExtractionPage $page, string $value): ExtractionField
{
    return ExtractionField::query()->create([
        'extraction_run_id' => $page->extraction_run_id,
        'extraction_page_id' => $page->id,
        'field_key' => 'amount', 'field_type' => 'string',
        'extracted_value' => $value, 'script_class' => 'en',
        'publisher_group' => 'dgcivil-bangladesh', 'confidence' => 84.0,
        'nonconformity_score' => 0.16, 'decision' => 'pending',
        'evidence_page_number' => 1, 'evidence_offset_start' => 0, 'evidence_offset_end' => 5,
    ]);
}

it('records a value it cannot find as unknown rather than as wrong', function (): void {
    // Scoring it at the maximum said "as bad as the worst misreading" about 57
    // fields that were read correctly and merely could not be found again, and
    // cost the score its discrimination: 0.75 measured with them, 0.90 without.
    // A null score defers by rule and stays out of calibration, and a field
    // never accepted cannot contribute to P(accepted AND wrong).
    $page = ExtractionPage::factory()->create(['recognized_words' => null]);
    $field = scorableField($page, '1,25,000.50');

    app(SecondReadScorer::class)->scorePage($page);
    $field->refresh();

    expect($field->nonconformity_score)->toBeNull()
        ->and($field->score_basis)->toBe('unlocatable')
        ->and($field->second_read_value)->toBeNull()
        // Excluded from calibration by the same scope that keeps unscored
        // fields out of it.
        ->and(ExtractionField::query()->calibratable()->count())->toBe(0);
});

it('reads Bengali and Latin digits as the same reading', function (): void {
    // Legacy-font pages store Bengali numerals as the Latin bytes that render
    // them, so 216.9 and ২১৬.৯ are one reading written two ways. Scoring that
    // as disagreement would fire on every native page in the corpus.
    $scorer = app(SecondReadScorer::class);
    $canonical = (new ReflectionClass($scorer))->getMethod('canonical');

    expect($canonical->invoke($scorer, '২১৬.৯'))->toBe('216.9')
        ->and($canonical->invoke($scorer, '216.9'))->toBe('216.9')
        // Everything that is not part of a figure is dropped, so a stray letter
        // from the recognizer does not read as a disagreement.
        ->and($canonical->invoke($scorer, 'টাকা ২১৬.৯ কোটি'))->toBe('216.9');
});

it('scores a disagreement above an agreement', function (): void {
    $scorer = app(SecondReadScorer::class);
    $distance = (new ReflectionClass($scorer))->getMethod('distance');

    // 889908 re-read as 885508 is two digits wrong in six.
    expect($distance->invoke($scorer, '889908', '885508'))->toBeGreaterThan(0.0)
        ->and($distance->invoke($scorer, '889908', '889908'))->toBe(0.0)
        // A misplaced separator differs by one character and is a hundredfold
        // error, which is why grouping carries its own weight in the score.
        ->and($distance->invoke($scorer, '2,96,106', '296,106'))->toBeLessThan(0.2);
});

it('reports the score as undecidable when only one outcome has been judged', function (): void {
    // A set with no errors cannot show a score separating outcomes or failing
    // to. Reporting 0.5 there would be inventing a number.
    $page = scorablePage('100.00');
    $field = scorableField($page, '100.00');
    $field->forceFill(['is_correct' => true, 'gold_source' => 'reviewer'])->save();

    $report = app(ScoreDiscriminationReport::class)->build();

    expect($report['overall']['auc'])->toBeNull()
        ->and($report['overall']['reason'])->toContain('Undecidable');
});

it('measures whether wrong readings score above right ones', function (): void {
    $page = scorablePage('100.00');

    $wrong = scorableField($page, '100.00');
    $wrong->forceFill(['is_correct' => false, 'gold_source' => 'reviewer', 'nonconformity_score' => 0.8])->save();

    $right = scorableField($page, '200.00');
    $right->forceFill(['is_correct' => true, 'gold_source' => 'reviewer', 'nonconformity_score' => 0.1])->save();

    // One wrong field scoring above one correct field is a perfect ordering of
    // the only pair there is.
    expect(app(ScoreDiscriminationReport::class)->build()['overall']['auc'])->toBe(1.0);
});
