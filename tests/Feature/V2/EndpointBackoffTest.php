<?php

use App\Models\SourceCrawlRun;
use App\Models\SourceEndpoint;
use App\Services\Ingestion\SourceAcquisitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function failingRun(SourceEndpoint $endpoint): SourceCrawlRun
{
    return SourceCrawlRun::factory()->create([
        'source_endpoint_id' => $endpoint->id,
        'status' => 'fetching',
        'discovered_count' => 3,
        'fetched_count' => 0,
        'failure_count' => 0,
    ]);
}

it('leaves a publisher alone for a while after it stops answering', function (): void {
    // Before this, an endpoint marked failing was dispatched again on the next
    // tick and every queued resource retried. Ninety-three failed jobs
    // accumulated against one host, which now refuses the connection outright.
    $endpoint = SourceEndpoint::factory()->create(['failure_streak' => 0, 'backoff_until' => null]);

    app(SourceAcquisitionService::class)->recordFetchFailure(failingRun($endpoint), 'Connection refused');
    $endpoint->refresh();

    expect($endpoint->failure_streak)->toBe(1)
        ->and($endpoint->backoff_until)->not->toBeNull()
        ->and($endpoint->isDue())->toBeFalse();
});

it('waits longer each time the publisher refuses again', function (): void {
    $endpoint = SourceEndpoint::factory()->create(['failure_streak' => 0, 'backoff_until' => null]);
    $service = app(SourceAcquisitionService::class);

    $service->recordFetchFailure(failingRun($endpoint), 'Connection refused');
    $first = $endpoint->refresh()->backoff_until;

    $service->recordFetchFailure(failingRun($endpoint), 'Connection refused');
    $second = $endpoint->refresh()->backoff_until;

    expect($second->greaterThan($first))->toBeTrue()
        ->and($endpoint->failure_streak)->toBe(2);
});

it('pauses an endpoint that keeps refusing, rather than backing off for ever', function (): void {
    // Backing off silently would leave a dead source looking merely quiet.
    config(['civiclens.ingestion.backoff.pause_after' => 3]);
    $endpoint = SourceEndpoint::factory()->create(['failure_streak' => 0, 'paused_at' => null]);
    $service = app(SourceAcquisitionService::class);

    foreach (range(1, 3) as $ignored) {
        $service->recordFetchFailure(failingRun($endpoint), 'Connection refused');
    }

    $endpoint->refresh();

    expect($endpoint->paused_at)->not->toBeNull()
        ->and($endpoint->last_error)->toContain('Paused after 3 consecutive failures');
});

it('clears the record when the publisher answers again', function (): void {
    // One bad afternoon should not quietly retire a healthy publisher.
    $endpoint = SourceEndpoint::factory()->create([
        'failure_streak' => 4,
        'backoff_until' => now()->addHours(2),
        'last_crawled_at' => now()->subDays(1),
    ]);

    $run = SourceCrawlRun::factory()->create([
        'source_endpoint_id' => $endpoint->id,
        'status' => 'fetching',
        'discovered_count' => 1,
        'fetched_count' => 0,
    ]);

    // Driven the way the fetch job drives it, since completion is internal to
    // the service rather than part of its public surface.
    $service = app(SourceAcquisitionService::class);
    (new ReflectionClass($service))->getMethod('recordFetchCompletion')->invoke($service, $run, false);
    $endpoint->refresh();

    expect($endpoint->failure_streak)->toBe(0)
        ->and($endpoint->backoff_until)->toBeNull()
        ->and($endpoint->isDue())->toBeTrue();
});

it('does not blame a publisher for our own pause', function (): void {
    // A paused endpoint declines its own fetches. Counting that as the host
    // failing would punish a publisher for an operator's decision, and would
    // extend the backoff every time someone paused it deliberately.
    $endpoint = SourceEndpoint::factory()->create([
        'failure_streak' => 0,
        'backoff_until' => null,
        'paused_at' => now(),
    ]);

    app(SourceAcquisitionService::class)->recordFetchFailure(
        failingRun($endpoint),
        'Source endpoint was paused before artifact retrieval.',
    );

    expect($endpoint->refresh()->failure_streak)->toBe(0)
        ->and($endpoint->backoff_until)->toBeNull();
});
