<?php

use App\Data\Intelligence\PeerObservation;
use App\Services\Intelligence\PeerComparisonService;

function peer(string $ref, float $value, string $nature = 'works', string $method = 'open', string $region = 'bogra'): PeerObservation
{
    return new PeerObservation($ref, $value, $nature, $method, $region);
}

/** @return list<PeerObservation> */
function cohortOf(int $count, float $value = 1_000_000.0): array
{
    return array_map(fn (int $i): PeerObservation => peer('peer-'.$i, $value), range(1, $count));
}

it('refuses to compare against too few peers', function (): void {
    $result = app(PeerComparisonService::class)->compare(peer('subject', 5_000_000.0), cohortOf(3));

    expect($result->comparable())->toBeFalse()
        ->and($result->cohortSize)->toBe(3)
        ->and($result->ratioToMedian)->toBeNull()
        ->and($result->statement)->toContain('not informative');
});

it('positions a value against its peer median', function (): void {
    $result = app(PeerComparisonService::class)->compare(peer('subject', 2_000_000.0), cohortOf(10));

    expect($result->comparable())->toBeTrue()
        ->and($result->cohortMedian)->toBe(1_000_000.0)
        ->and($result->ratioToMedian)->toBe(2.0)
        ->and($result->cohortReferences)->toHaveCount(10);
});

it('excludes procurements outside the cohort and the subject itself', function (): void {
    $candidates = array_merge(
        cohortOf(9),
        [peer('other-nature', 1.0, nature: 'goods'), peer('other-region', 1.0, region: 'dhaka'), peer('subject', 99.0)],
    );

    $result = app(PeerComparisonService::class)->compare(peer('subject', 1_000_000.0), $candidates);

    expect($result->cohortSize)->toBe(9)
        ->and($result->cohortReferences)->not->toContain('subject')
        ->and($result->cohortReferences)->not->toContain('other-nature')
        ->and($result->cohortReferences)->not->toContain('other-region');
});

it('does not let one extreme peer define the baseline', function (): void {
    // Mean would be dragged far above every real value by the outlier; the
    // median must not be. A cohort containing one genuine outlier would
    // otherwise excuse the next one.
    $candidates = array_merge(cohortOf(9), [peer('extreme', 900_000_000.0)]);

    $result = app(PeerComparisonService::class)->compare(peer('subject', 1_000_000.0), $candidates);
    $mean = array_sum(array_map(fn ($c) => $c->value, $candidates)) / count($candidates);

    expect($result->cohortMedian)->toBe(1_000_000.0)
        ->and($result->cohortMedian)->toBeLessThan($mean / 10);
});

it('never states a conclusion about conduct', function (): void {
    $service = app(PeerComparisonService::class);
    $statements = [
        $service->compare(peer('a', 9_000_000.0), cohortOf(10))->statement,
        $service->compare(peer('b', 1_000_000.0), cohortOf(10))->statement,
        $service->compare(peer('c', 100.0), cohortOf(10))->statement,
        $service->compare(peer('d', 1.0), cohortOf(2))->statement,
    ];

    foreach ($statements as $statement) {
        foreach (['corrupt', 'fraud', 'overpriced', 'inflated', 'suspicious', 'guilty'] as $forbidden) {
            expect(mb_strtolower($statement))->not->toContain($forbidden);
        }
    }

    expect($statements[0])->toContain('not evidence of overpricing');
});

it('describes direction without asserting wrongdoing', function (): void {
    $service = app(PeerComparisonService::class);

    expect($service->compare(peer('high', 3_000_000.0), cohortOf(10))->statement)->toContain('higher than')
        ->and($service->compare(peer('low', 200_000.0), cohortOf(10))->statement)->toContain('lower than')
        ->and($service->compare(peer('same', 1_000_000.0), cohortOf(10))->statement)->toContain('close to');
});

it('reports a robust deviation only when peers actually vary', function (): void {
    $service = app(PeerComparisonService::class);
    $identical = $service->compare(peer('subject', 2_000_000.0), cohortOf(10));

    $varied = $service->compare(peer('subject', 2_000_000.0), array_map(
        fn (int $i): PeerObservation => peer('p-'.$i, 900_000.0 + ($i * 25_000)),
        range(1, 10),
    ));

    expect($identical->robustDeviation)->toBeNull()
        ->and($varied->robustDeviation)->not->toBeNull()
        ->and($varied->robustDeviation)->toBeGreaterThan(0.0);
});

it('names every peer it measured against', function (): void {
    $result = app(PeerComparisonService::class)->compare(peer('subject', 1_500_000.0), cohortOf(12));

    expect($result->cohortReferences)->toHaveCount(12)
        ->and($result->toArray()['cohort_references'])->toBe($result->cohortReferences)
        ->and($result->toArray()['comparable'])->toBeTrue();
});
