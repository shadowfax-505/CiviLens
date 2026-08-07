<?php

use App\Data\Extraction\RecognizedWord;
use App\Exceptions\Extraction\ExtractionFailed;
use App\Models\ExtractionField;
use App\Models\ExtractionRun;
use App\Services\Extraction\BenchmarkEvaluationService;
use App\Services\Extraction\BenchmarkManifestReader;
use App\Services\Extraction\ConformalCalibrator;
use App\Services\Extraction\FieldValueMatcher;
use App\Services\Extraction\KeyValueExtractor;
use App\Services\Extraction\PageRasterizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

function benchmarkToolchainMissing(): bool
{
    static $missing = null;

    if ($missing === null) {
        $missing = false;

        foreach ([['pdftoppm', '-v'], ['tesseract', '--version']] as [$binary, $flag]) {
            $process = new Process([$binary, $flag]);
            $process->run();

            if (! $process->isSuccessful()) {
                $missing = true;
            }
        }
    }

    return $missing;
}

/**
 * Build a manifest directory containing a real rendered page whose text is
 * known, so gold values can be genuinely right or genuinely wrong.
 *
 * @param  array<string, string>  $goldFields
 */
function benchmarkManifest(array $goldFields, string $publisher = 'mof', string $script = 'en', string $split = 'calibration'): string
{
    $root = sys_get_temp_dir().'/civiclens-benchmark-'.bin2hex(random_bytes(6));
    mkdir($root.'/images', 0700, true);

    $image = app(PageRasterizer::class)->rasterize(
        __DIR__.'/../../Fixtures/Extraction/scanned-text.pdf',
        1,
        150,
    );
    copy($image, $root.'/images/page-1.png');
    app(PageRasterizer::class)->discard($image);

    file_put_contents($root.'/manifest.json', json_encode([
        'benchmark' => 'fixture',
        'license' => 'generated-for-tests',
        'pages' => [[
            'image' => 'images/page-1.png',
            'publisher_group' => $publisher,
            'script_class' => $script,
            'split' => $split,
            'page_number' => 1,
            'fields' => $goldFields,
        ]],
    ], JSON_THROW_ON_ERROR));

    return $root.'/manifest.json';
}

it('rejects a manifest that reaches outside its own directory', function (): void {
    $root = sys_get_temp_dir().'/civiclens-benchmark-'.bin2hex(random_bytes(6));
    mkdir($root, 0700, true);
    file_put_contents($root.'/manifest.json', json_encode([
        'pages' => [[
            'image' => '../../../../etc/hosts',
            'publisher_group' => 'mof',
            'script_class' => 'en',
            'split' => 'calibration',
            'fields' => ['a' => 'b'],
        ]],
    ], JSON_THROW_ON_ERROR));

    expect(fn () => app(BenchmarkManifestReader::class)->read($root.'/manifest.json'))
        ->toThrow(ExtractionFailed::class);
});

it('rejects manifests with unusable structure', function (): void {
    $reader = app(BenchmarkManifestReader::class);
    $root = sys_get_temp_dir().'/civiclens-benchmark-'.bin2hex(random_bytes(6));
    mkdir($root, 0700, true);

    $write = function (array $payload) use ($root): string {
        file_put_contents($root.'/manifest.json', json_encode($payload, JSON_THROW_ON_ERROR));

        return $root.'/manifest.json';
    };

    expect(fn () => $reader->read($root.'/missing.json'))->toThrow(ExtractionFailed::class, 'not found')
        ->and(fn () => $reader->read($write(['pages' => []])))->toThrow(ExtractionFailed::class, 'no pages')
        ->and(fn () => $reader->read($write(['nope' => 1])))->toThrow(ExtractionFailed::class, 'pages array')
        ->and(fn () => $reader->read($write(['pages' => [['image' => 'x', 'publisher_group' => 'a', 'script_class' => 'klingon', 'fields' => ['a' => 'b']]]])))
        ->toThrow(ExtractionFailed::class, 'script_class');
});

it('folds bengali digits and whitespace but does not invent agreement', function (): void {
    $matcher = new FieldValueMatcher;

    expect($matcher->matches('১২৩৪৫', 'reference 12345 issued'))->toBeTrue()
        ->and($matcher->matches('Ministry  of   Finance', 'the ministry of finance said'))->toBeTrue()
        ->and($matcher->matches('1000000', 'allocation 1000000 BDT'))->toBeTrue()
        ->and($matcher->matches('Ministry of Health', 'Ministry of Finance'))->toBeFalse()
        ->and($matcher->matches('1000000', 'allocation 1000001 BDT'))->toBeFalse()
        ->and($matcher->matches('', 'anything'))->toBeFalse();
});

it('records real outcomes from labels present on the page', function (): void {
    // The fixture page reads "Ministry of Finance budget line item 000
    // allocation 1000000 BDT". Field keys are the printed labels; the gold value
    // is what should follow them.
    $manifest = benchmarkManifest([
        'allocation' => '1000000',
        'Finance' => 'budget',
        'NoSuchLabelOnThisPage' => 'irrelevant',
    ]);

    $summary = app(BenchmarkEvaluationService::class)->evaluate($manifest, 'fixture');

    expect($summary['fields'])->toBe(3)
        ->and($summary['correct'])->toBe(2)
        ->and($summary['field_accuracy'])->toBe(0.6667);

    $fields = ExtractionField::query()->get()->keyBy('field_key');

    expect($fields['allocation']->is_correct)->toBeTrue()
        ->and($fields['allocation']->extracted_value)->toContain('1000000')
        ->and($fields['allocation']->gold_source)->toBe('benchmark')
        ->and($fields['NoSuchLabelOnThisPage']->is_correct)->toBeFalse()
        ->and($fields['NoSuchLabelOnThisPage']->extracted_value)->toBeNull()
        ->and($fields['NoSuchLabelOnThisPage']->nonconformity_score)->toBe(1.0)
        ->and($fields['allocation']->publisher_group)->toBe('mof');
})->skip(fn (): bool => benchmarkToolchainMissing(), 'poppler or tesseract is not installed');

it('records a benchmark run without fabricating a source artifact', function (): void {
    app(BenchmarkEvaluationService::class)->evaluate(benchmarkManifest(['phrase' => 'Ministry of Finance']), 'fixture');

    $run = ExtractionRun::query()->sole();

    expect($run->benchmark)->toBe('fixture')
        ->and($run->source_artifact_version_id)->toBeNull()
        ->and($run->status)->toBe('completed')
        ->and($run->engine)->toBe('tesseract')
        ->and($run->engine_version)->not->toBe('pending')
        ->and($run->page_count)->toBe(1);
})->skip(fn (): bool => benchmarkToolchainMissing(), 'poppler or tesseract is not installed');

it('produces fields the conformal calibrator can consume', function (): void {
    app(BenchmarkEvaluationService::class)->evaluate(
        benchmarkManifest(['allocation' => '1000000', 'Finance' => 'budget', 'item' => '000']),
        'fixture',
    );

    expect(ExtractionField::query()->calibratable()->count())->toBe(3);

    $calibration = app(ConformalCalibrator::class)->calibrate(0.5)->get('mof|en');

    expect($calibration)->not->toBeNull()
        ->and($calibration->calibrationSize)->toBe(3);
})->skip(fn (): bool => benchmarkToolchainMissing(), 'poppler or tesseract is not installed');

it('locates the shortest run of words backing a value and returns their confidences', function (): void {
    $matcher = new FieldValueMatcher;
    $words = [
        new RecognizedWord('Ministry', 95.0),
        new RecognizedWord('of', 88.0),
        new RecognizedWord('Finance', 41.0),
        new RecognizedWord('allocation', 77.0),
        new RecognizedWord('Ministry', 60.0),
    ];

    expect($matcher->locate('Ministry of Finance', $words))->toBe([95.0, 88.0, 41.0])
        ->and($matcher->locate('allocation', $words))->toBe([77.0])
        ->and($matcher->locate('Department of Health', $words))->toBeNull()
        ->and($matcher->locate('', $words))->toBeNull()
        ->and($matcher->locate('anything', []))->toBeNull();
});

it('folds bengali digits when locating a span', function (): void {
    $matcher = new FieldValueMatcher;
    $words = [new RecognizedWord('মোট', 90.0), new RecognizedWord('১২৩৪৫', 33.0)];

    expect($matcher->locate('12345', $words))->toBe([33.0]);
});

it('gives fields on one page distinct nonconformity scores', function (): void {
    // The defect this guards: scoring every field with the page mean ties them
    // to one value, and conformal calibration can only accept or reject a run of
    // ties whole, so no threshold ever satisfies the bound.
    $manifest = benchmarkManifest([
        'allocation' => '1000000',
        'Finance' => 'budget',
        'item' => '000',
        'NoSuchLabelOnThisPage' => 'irrelevant',
    ]);

    app(BenchmarkEvaluationService::class)->evaluate($manifest, 'fixture');

    $scores = ExtractionField::query()->pluck('nonconformity_score')->all();
    $predicted = ExtractionField::query()->whereNotNull('extracted_value')->pluck('nonconformity_score');

    expect(count($scores))->toBe(4)
        ->and(collect($scores)->unique()->count())->toBeGreaterThan(1)
        ->and($predicted->count())->toBeGreaterThan(1)
        ->and(ExtractionField::query()->whereNull('extracted_value')->sole()->nonconformity_score)->toBe(1.0);
})->skip(fn (): bool => benchmarkToolchainMissing(), 'poppler or tesseract is not installed');

it('scores a field by its weakest supporting word rather than the average', function (): void {
    $matcher = new FieldValueMatcher;
    $words = [new RecognizedWord('alpha', 99.0), new RecognizedWord('beta', 10.0)];

    $span = $matcher->locate('alpha beta', $words);

    expect($span)->toBe([99.0, 10.0])
        ->and(min($span))->toBe(10.0);
});

/** @return list<RecognizedWord> */
function formLine(array $spec): array
{
    $words = [];
    $x = 100;

    foreach ($spec as [$text, $conf, $gap]) {
        $x += $gap;
        $words[] = new RecognizedWord($text, $conf, $x, 200, 20 * mb_strlen($text), 30);
        $x += 20 * mb_strlen($text);
    }

    return $words;
}

it('predicts a value from the label position without consulting the gold', function (): void {
    $words = formLine([['Name', 95.0, 0], [':', 90.0, 5], ['Alvi', 72.0, 10], ['Sarkar', 41.0, 10]]);

    $prediction = app(KeyValueExtractor::class)->extract('Name', $words);

    expect($prediction['value'])->toBe(': Alvi Sarkar')
        ->and($prediction['confidences'])->toBe([90.0, 72.0, 41.0])
        ->and(min($prediction['confidences']))->toBe(41.0);
});

it('stops reading at a column gap so it does not swallow the next label', function (): void {
    // "Name: Alvi" then a wide gap then "Age: 40" -- reading to end of line would
    // merge two fields into one value.
    $words = formLine([['Name', 95.0, 0], ['Alvi', 80.0, 10], ['Age', 93.0, 400], ['40', 88.0, 10]]);

    $prediction = app(KeyValueExtractor::class)->extract('Name', $words);

    expect($prediction['value'])->toBe('Alvi')
        ->and($prediction['confidences'])->toBe([80.0]);
});

it('returns nothing when the label is absent or nothing follows it', function (): void {
    $extractor = app(KeyValueExtractor::class);
    $words = formLine([['Name', 95.0, 0], ['Alvi', 80.0, 10]]);

    expect($extractor->extract('Address', $words))->toBeNull()
        ->and($extractor->extract('Alvi', $words))->toBeNull()
        ->and($extractor->extract('', $words))->toBeNull()
        ->and($extractor->extract('Name', []))->toBeNull();
});

it('ignores words on other lines when reading a value', function (): void {
    $words = [
        new RecognizedWord('Name', 95.0, 100, 200, 80, 30),
        new RecognizedWord('Alvi', 80.0, 200, 205, 80, 30),
        new RecognizedWord('Elsewhere', 70.0, 300, 600, 180, 30),
    ];

    $prediction = app(KeyValueExtractor::class)->extract('Name', $words);

    expect($prediction['value'])->toBe('Alvi');
});

it('can be wrong on its own terms rather than by construction', function (): void {
    // The predictor reads what is beside the label. If that is not the gold
    // value, the field is incorrect *and* still carries a real confidence --
    // which is exactly what a non-circular score requires.
    $words = formLine([['Name', 95.0, 0], ['Wrong', 66.0, 10]]);

    $prediction = app(KeyValueExtractor::class)->extract('Name', $words);
    $matcher = new FieldValueMatcher;

    expect($prediction['value'])->toBe('Wrong')
        ->and($matcher->matches('Alvi Sarkar', $prediction['value']))->toBeFalse()
        ->and(min($prediction['confidences']))->toBe(66.0);
});
