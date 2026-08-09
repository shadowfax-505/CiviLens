<?php

use App\Models\DiscoveredResource;
use App\Models\SourceEndpoint;
use App\Models\TenderObservation;
use App\Services\Ingestion\TenderObservationRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @param array<string, mixed> $metadata */
function observedResource(array $metadata, ?string $externalId = '1316153', ?SourceEndpoint $endpoint = null): DiscoveredResource
{
    $endpoint ??= SourceEndpoint::factory()->create();

    return DiscoveredResource::factory()->create([
        'source_endpoint_id' => $endpoint->id,
        'external_id' => $externalId,
        'metadata' => $metadata,
    ])->load('endpoint');
}

it('records what a publisher said about a tender', function (): void {
    $recorder = app(TenderObservationRecorder::class);

    $result = $recorder->record([observedResource([
        'reference_number' => '37.07.0000.000.014.20.101.24-7311-1',
        'status' => 'Live',
        'procurement_nature' => 'Works',
        'published_on' => '07-07-2026',
    ])]);

    $observation = TenderObservation::query()->sole();

    expect($result['recorded'])->toBe(1)
        ->and($observation->external_id)->toBe('1316153')
        ->and($observation->status)->toBe('Live')
        ->and($observation->procurement_nature)->toBe('Works')
        ->and($observation->published_on->toDateString())->toBe('2026-07-07');
});

it('does not record the same observation twice', function (): void {
    $recorder = app(TenderObservationRecorder::class);
    $endpoint = SourceEndpoint::factory()->create();
    $metadata = ['reference_number' => 'REF-A', 'status' => 'Live', 'procurement_nature' => 'Works', 'published_on' => '01-08-2026'];

    $recorder->record([observedResource($metadata, endpoint: $endpoint)]);
    $second = $recorder->record([observedResource($metadata, endpoint: $endpoint)]);

    expect($second['recorded'])->toBe(0)
        ->and($second['unchanged'])->toBe(1)
        ->and(TenderObservation::query()->count())->toBe(1);
});

it('records a changed value as new history rather than overwriting', function (): void {
    // A publisher correcting itself must be visible, not erased.
    $recorder = app(TenderObservationRecorder::class);
    $endpoint = SourceEndpoint::factory()->create();

    $recorder->record([observedResource(['status' => 'Live', 'reference_number' => 'REF-A'], endpoint: $endpoint)]);
    $recorder->record([observedResource(['status' => 'Archived', 'reference_number' => 'REF-A'], endpoint: $endpoint)]);

    expect(TenderObservation::query()->count())->toBe(2)
        ->and(TenderObservation::query()->pluck('status')->all())->toBe(['Live', 'Archived']);
});

it('refuses to edit or delete a recorded observation', function (): void {
    app(TenderObservationRecorder::class)->record([observedResource(['status' => 'Live'])]);
    $observation = TenderObservation::query()->sole();

    expect($observation->delete())->toBeFalse();

    $observation->forceFill(['status' => 'Rewritten'])->save();

    expect($observation->fresh()->status)->toBe('Live');
});

it('leaves an ambiguous or invalid date null rather than guessing', function (): void {
    // A wrong date would silently reorder a timeline.
    $recorder = app(TenderObservationRecorder::class);

    foreach (['07-07-26', '31-02-2026', 'sometime', ''] as $raw) {
        $recorder->record([observedResource(['status' => 'Live', 'published_on' => $raw, 'reference_number' => 'R'.$raw])]);
    }

    expect(TenderObservation::query()->whereNotNull('published_on')->count())->toBe(0)
        ->and(TenderObservation::query()->count())->toBe(4);
});

it('reads unambiguous four-digit-year dates in both orders', function (): void {
    $recorder = app(TenderObservationRecorder::class);
    $recorder->record([observedResource(['published_on' => '01.08.2026', 'reference_number' => 'A'], '111')]);
    $recorder->record([observedResource(['published_on' => '2026-08-02', 'reference_number' => 'B'], '222')]);

    expect(TenderObservation::query()->orderBy('external_id')->pluck('published_on')->map(fn ($d) => $d?->toDateString())->all())
        ->toBe(['2026-08-01', '2026-08-02']);
});

it('skips a resource with no external identifier', function (): void {
    $result = app(TenderObservationRecorder::class)->record([observedResource(['status' => 'Live'], null)]);

    expect($result['skipped'])->toBe(1)
        ->and(TenderObservation::query()->count())->toBe(0);
});

it('bounds stored text so a hostile listing cannot bloat a record', function (): void {
    app(TenderObservationRecorder::class)->record([observedResource([
        'reference_number' => str_repeat('A', 5000),
        'status' => str_repeat('B', 500),
    ])]);

    $observation = TenderObservation::query()->sole();

    expect(mb_strlen((string) $observation->reference_number))->toBe(300)
        ->and(mb_strlen((string) $observation->status))->toBe(64);
});
