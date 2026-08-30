<?php

use App\Models\DiscoveredResource;
use App\Models\SourceArtifactVersion;
use App\Models\SourceCrawlRun;
use App\Models\SourceEndpoint;
use App\Services\Ingestion\DiscoveryPacing;
use App\Services\Ingestion\SourceAcquisitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Records the waits and the resources handed over, taking neither. */
function fetchSpies(array &$waits, array &$acquired): void
{
    app()->instance(DiscoveryPacing::class, new class($waits) extends DiscoveryPacing
    {
        /** @param list<int> $waits */
        public function __construct(private array &$waits) {}

        public function wait(int $seconds): void
        {
            $this->waits[] = $seconds;
        }
    });

    app()->instance(SourceAcquisitionService::class, new class($acquired) extends SourceAcquisitionService
    {
        /** @param list<int> $acquired */
        public function __construct(private array &$acquired) {}

        public function acquire(DiscoveredResource $resource, SourceCrawlRun $run): SourceArtifactVersion
        {
            $this->acquired[] = (int) $resource->getKey();

            // Must succeed. An earlier version of this double threw, the
            // command counted it a failure, and the pacing branch was never
            // reached — so the test passed while asserting nothing about
            // pacing, which is worse than not having it.
            return SourceArtifactVersion::factory()->create([
                'discovered_resource_id' => $resource->getKey(),
            ]);
        }
    });
}

it('paces one request per interval instead of queueing thousands', function (): void {
    // Queued, these do not wait — the RateLimited middleware releases a job
    // rather than sleeping, so every thirty seconds the whole queue becomes
    // available, each job is pulled, refused and written back, and a job can
    // spend its two hundred attempts churning without ever being fetched.
    $endpoint = SourceEndpoint::factory()->create([
        'paused_at' => null,
        'rate_limit_per_minute' => 6,
    ]);

    SourceCrawlRun::factory()->create(['source_endpoint_id' => $endpoint->id]);

    foreach (range(1, 3) as $ignored) {
        DiscoveredResource::factory()->create([
            'source_endpoint_id' => $endpoint->id,
            'resource_type' => 'document',
            'status' => 'discovered',
        ]);
    }

    $waits = [];
    $acquired = [];
    fetchSpies($waits, $acquired);

    $this->artisan('civiclens:fetch-pending', ['--endpoint' => [$endpoint->id]])->assertExitCode(0);

    // Six a minute is one every ten seconds. Two intervals for three
    // requests: nothing waits before the first, since pacing the opening
    // request delays every run for nothing.
    expect($acquired)->toHaveCount(3)
        ->and($waits)->toBe([10, 10]);
});

it('leaves a paused endpoint alone', function (): void {
    // A paused endpoint is a governance decision, and a command that fetched
    // from one anyway would make the pause decorative.
    $endpoint = SourceEndpoint::factory()->create(['paused_at' => now()]);
    SourceCrawlRun::factory()->create(['source_endpoint_id' => $endpoint->id]);

    DiscoveredResource::factory()->create([
        'source_endpoint_id' => $endpoint->id,
        'resource_type' => 'document',
        'status' => 'discovered',
    ]);

    $waits = [];
    $acquired = [];
    fetchSpies($waits, $acquired);

    $this->artisan('civiclens:fetch-pending', ['--endpoint' => [$endpoint->id]])->assertExitCode(0);

    expect($acquired)->toBe([]);
});

it('does not refetch what is already acquired', function (): void {
    $endpoint = SourceEndpoint::factory()->create(['paused_at' => null, 'rate_limit_per_minute' => 6]);
    SourceCrawlRun::factory()->create(['source_endpoint_id' => $endpoint->id]);

    DiscoveredResource::factory()->create([
        'source_endpoint_id' => $endpoint->id,
        'resource_type' => 'document',
        'status' => 'acquired',
    ]);

    $waits = [];
    $acquired = [];
    fetchSpies($waits, $acquired);

    $this->artisan('civiclens:fetch-pending', ['--endpoint' => [$endpoint->id]])->assertExitCode(0);

    expect($acquired)->toBe([]);
});
