<?php

use App\Models\SourcePublisher;
use App\Models\TenderObservation;
use App\Services\Intelligence\NoticeRevisionDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @param array<string, string|null> $attributes */
function observe(SourcePublisher $publisher, string $externalId, array $attributes, string $observedAt): TenderObservation
{
    $fields = [
        'reference_number' => $attributes['reference_number'] ?? 'REF-A',
        'status' => $attributes['status'] ?? 'Live',
        'procurement_nature' => $attributes['procurement_nature'] ?? 'Works',
        'published_on_raw' => $attributes['published_on_raw'] ?? '01-08-2026',
    ];

    return TenderObservation::query()->create([
        'source_publisher_id' => $publisher->id,
        'external_id' => $externalId,
        ...$fields,
        'observation_hash' => hash('sha256', json_encode($fields, JSON_THROW_ON_ERROR)),
        'observed_at' => $observedAt,
    ]);
}

it('reports nothing for a notice observed once', function (): void {
    $publisher = SourcePublisher::factory()->create();
    observe($publisher, '111', [], '2026-08-01 09:00:00');

    expect(app(NoticeRevisionDetector::class)->detect($publisher->id))->toBe([]);
});

it('reports what changed between observations', function (): void {
    $publisher = SourcePublisher::factory()->create();
    observe($publisher, '111', ['status' => 'Live'], '2026-08-01 09:00:00');
    observe($publisher, '111', ['status' => 'Archived'], '2026-08-05 09:00:00');

    $found = app(NoticeRevisionDetector::class)->detect($publisher->id);

    expect($found)->toHaveCount(1)
        ->and($found[0]['external_id'])->toBe('111')
        ->and($found[0]['observations'])->toBe(2)
        ->and($found[0]['changes'])->toHaveCount(1)
        ->and($found[0]['changes'][0]['field'])->toBe('status')
        ->and($found[0]['changes'][0]['from'])->toBe('Live')
        ->and($found[0]['changes'][0]['to'])->toBe('Archived');
});

it('records several changes across a chain of revisions', function (): void {
    $publisher = SourcePublisher::factory()->create();
    observe($publisher, '111', ['status' => 'Live', 'procurement_nature' => 'Works'], '2026-08-01 09:00:00');
    observe($publisher, '111', ['status' => 'Amended', 'procurement_nature' => 'Works'], '2026-08-03 09:00:00');
    observe($publisher, '111', ['status' => 'Amended', 'procurement_nature' => 'Goods'], '2026-08-06 09:00:00');

    $found = app(NoticeRevisionDetector::class)->detect($publisher->id);

    expect($found[0]['observations'])->toBe(3)
        ->and($found[0]['changes'])->toHaveCount(2)
        ->and(array_column($found[0]['changes'], 'field'))->toBe(['status', 'procurement_nature']);
});

it('never states a conclusion about conduct', function (): void {
    $publisher = SourcePublisher::factory()->create();
    observe($publisher, '111', ['status' => 'Live'], '2026-08-01 09:00:00');
    observe($publisher, '111', ['status' => 'Cancelled'], '2026-08-02 09:00:00');

    $statement = app(NoticeRevisionDetector::class)->detect($publisher->id)[0]['statement'];

    foreach (['corrupt', 'fraud', 'suspicious', 'irregular', 'manipulat', 'guilty', 'improper'] as $forbidden) {
        expect(mb_strtolower($statement))->not->toContain($forbidden);
    }

    expect($statement)->toContain('not that anything was wrong')
        ->and($statement)->toContain('111');
});

it('keeps publishers separate', function (): void {
    $a = SourcePublisher::factory()->create(['slug' => 'a']);
    $b = SourcePublisher::factory()->create(['slug' => 'b']);
    observe($a, '111', ['status' => 'Live'], '2026-08-01 09:00:00');
    observe($a, '111', ['status' => 'Archived'], '2026-08-02 09:00:00');
    observe($b, '222', ['status' => 'Live'], '2026-08-01 09:00:00');

    expect(app(NoticeRevisionDetector::class)->detect($a->id))->toHaveCount(1)
        ->and(app(NoticeRevisionDetector::class)->detect($b->id))->toBe([]);
});

it('orders changes by when they were observed, not by insertion', function (): void {
    // Observations can arrive out of order if a crawl is replayed. The timeline
    // must reflect what the publisher did, not what the crawler did.
    $publisher = SourcePublisher::factory()->create();
    observe($publisher, '111', ['status' => 'Archived'], '2026-08-09 09:00:00');
    observe($publisher, '111', ['status' => 'Live'], '2026-08-01 09:00:00');

    $found = app(NoticeRevisionDetector::class)->detect($publisher->id);

    expect($found[0]['changes'][0]['from'])->toBe('Live')
        ->and($found[0]['changes'][0]['to'])->toBe('Archived')
        ->and($found[0]['first_observed_at'])->toContain('2026-08-01');
});

it('works from the first crawl without waiting for a cohort', function (): void {
    // Unlike peer comparison, one notice seen twice is enough.
    $publisher = SourcePublisher::factory()->create();
    observe($publisher, '111', ['reference_number' => 'REF-A'], '2026-08-01 09:00:00');
    observe($publisher, '111', ['reference_number' => 'REF-B'], '2026-08-02 09:00:00');

    expect(app(NoticeRevisionDetector::class)->detect($publisher->id))->toHaveCount(1);
});
