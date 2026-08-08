<?php

use App\Models\ExtractionExperiment;
use App\Services\Extraction\BornDigitalWordExtractor;
use App\Services\Extraction\ExtractionImprovementLoop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

function popplerAbsent(): bool
{
    static $missing = null;

    if ($missing === null) {
        $process = new Process(['pdftotext', '-v']);
        $process->run();
        $missing = ! $process->isSuccessful();
    }

    return $missing;
}

beforeEach(fn () => app(ExtractionImprovementLoop::class)->clearStop());

it('reads exact word geometry from a born-digital text layer', function (): void {
    $words = app(BornDigitalWordExtractor::class)->words(
        __DIR__.'/../../Fixtures/Extraction/native-text.pdf',
        1,
    );

    expect(count($words))->toBeGreaterThan(100)
        ->and($words[0]->text)->toBe('Ministry')
        ->and($words[0]->confidence)->toBe(BornDigitalWordExtractor::EXACT_CONFIDENCE)
        ->and($words[0]->width)->toBeGreaterThan(0)
        ->and($words[0]->height)->toBeGreaterThan(0)
        ->and($words[1]->left)->toBeGreaterThan($words[0]->left);
})->skip(fn (): bool => popplerAbsent(), 'poppler is not installed');

it('returns nothing for a page with no text layer instead of failing', function (): void {
    $words = app(BornDigitalWordExtractor::class)->words(
        __DIR__.'/../../Fixtures/Extraction/no-text-layer.pdf',
        1,
    );

    expect($words)->toBe([]);
})->skip(fn (): bool => popplerAbsent(), 'poppler is not installed');

it('records every candidate it measures', function (): void {
    $summary = app(ExtractionImprovementLoop::class)->run(
        'field-extraction',
        'fixture',
        [['threshold' => 1], ['threshold' => 2], ['threshold' => 3]],
        fn (array $config): array => ['fields' => 10, 'correct' => $config['threshold']],
    );

    expect($summary['iterations_run'])->toBe(3)
        ->and($summary['stopped_because'])->toBe('candidates exhausted')
        ->and($summary['best']['field_accuracy'])->toBe(0.3)
        ->and(ExtractionExperiment::query()->count())->toBe(3);
});

it('never promotes its own winner', function (): void {
    $summary = app(ExtractionImprovementLoop::class)->run(
        'field-extraction',
        'fixture',
        [['a' => 1], ['a' => 2]],
        fn (array $config): array => ['fields' => 4, 'correct' => $config['a']],
    );

    expect($summary['promoted'])->toBeNull()
        ->and($summary['promotion_note'])->toContain('human decision')
        ->and(ExtractionExperiment::query()->whereNotNull('promoted_at')->count())->toBe(0);
});

it('stops at the iteration limit', function (): void {
    $summary = app(ExtractionImprovementLoop::class)->run(
        'field-extraction',
        'fixture',
        [['a' => 1], ['a' => 2], ['a' => 3], ['a' => 4]],
        fn (): array => ['fields' => 1, 'correct' => 1],
        maxIterations: 2,
    );

    expect($summary['iterations_run'])->toBe(2)
        ->and($summary['stopped_because'])->toBe('iteration limit')
        ->and(ExtractionExperiment::query()->count())->toBe(2);
});

it('stops when the kill switch is set', function (): void {
    $loop = app(ExtractionImprovementLoop::class);
    $calls = 0;

    $summary = $loop->run('field-extraction', 'fixture', [['a' => 1], ['a' => 2]], function () use ($loop, &$calls): array {
        $calls++;
        $loop->requestStop();

        return ['fields' => 1, 'correct' => 1];
    });

    expect($calls)->toBe(1)
        ->and($summary['iterations_run'])->toBe(1)
        ->and($summary['stopped_because'])->toBe('kill switch');
});

it('retains a failed candidate rather than discarding it', function (): void {
    $summary = app(ExtractionImprovementLoop::class)->run(
        'field-extraction',
        'fixture',
        [['a' => 1], ['a' => 2]],
        function (array $config): array {
            if ($config['a'] === 1) {
                throw new RuntimeException('candidate blew up');
            }

            return ['fields' => 2, 'correct' => 2];
        },
    );

    $failed = ExtractionExperiment::query()->where('status', 'failed')->sole();

    expect($summary['iterations_run'])->toBe(2)
        ->and($failed->failure_reason)->toContain('blew up')
        ->and($failed->field_accuracy)->toBeNull()
        ->and($summary['best']['iteration'])->toBe(2);
});

it('refuses to delete or rewrite a finished measurement', function (): void {
    app(ExtractionImprovementLoop::class)->run(
        'field-extraction',
        'fixture',
        [['a' => 1]],
        fn (): array => ['fields' => 4, 'correct' => 1],
    );

    $experiment = ExtractionExperiment::query()->sole();

    expect($experiment->delete())->toBeFalse();

    $experiment->forceFill(['correct' => 4, 'field_accuracy' => 1.0])->save();

    expect($experiment->fresh()->field_accuracy)->toBe(0.25)
        ->and($experiment->fresh()->correct)->toBe(1);
});

it('permits only the human promotion decision after a run finishes', function (): void {
    app(ExtractionImprovementLoop::class)->run(
        'field-extraction',
        'fixture',
        [['a' => 1]],
        fn (): array => ['fields' => 2, 'correct' => 1],
    );

    $experiment = ExtractionExperiment::query()->sole();
    $experiment->forceFill(['promoted_at' => now()])->save();

    expect($experiment->fresh()->isPromoted())->toBeTrue()
        ->and($experiment->fresh()->field_accuracy)->toBe(0.5);
});
