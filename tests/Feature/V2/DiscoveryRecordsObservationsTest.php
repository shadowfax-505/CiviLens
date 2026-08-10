<?php

use App\Contracts\Ingestion\SourceConnector;
use App\Data\Ingestion\CrawlCursor;
use App\Data\Ingestion\DiscoveredResourceData;
use App\Data\Ingestion\DiscoveryBatch;
use App\Models\SourceEndpoint;
use App\Models\SourcePublisher;
use App\Models\TenderObservation;
use App\Services\Ingestion\SourceAcquisitionService;
use App\Services\Ingestion\SourceConnectorRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function listingEndpoint(): SourceEndpoint
{
    $publisher = SourcePublisher::query()->create([
        'slug' => 'egp',
        'name' => 'Bangladesh Public Procurement Authority',
        'source_class' => 'government',
        'canonical_url' => 'https://www.eprocure.gov.bd/',
        'attribution_name' => 'Bangladesh Public Procurement Authority',
        'is_active' => true,
        'metadata' => [],
    ]);

    return $publisher->endpoints()->create([
        'name' => 'Public tender and proposal listing',
        'connector_type' => 'egp_tender_listing',
        'base_url' => 'https://www.eprocure.gov.bd/TenderDetailsServlet',
        'allowed_hosts' => ['www.eprocure.gov.bd'],
        'allowed_path_prefixes' => ['/TenderDetailsServlet'],
        'access_decision' => 'operator-authorised-public-notices',
        'access_reviewed_at' => now(),
    ]);
}

/** @param list<array{id: string, status: string}> $rows */
function bindListingConnector(array $rows): void
{
    $connector = new class($rows) implements SourceConnector
    {
        /** @param list<array{id: string, status: string}> $rows */
        public function __construct(private readonly array $rows) {}

        public function discover(SourceEndpoint $endpoint, CrawlCursor $cursor): DiscoveryBatch
        {
            $resources = [];

            foreach ($this->rows as $row) {
                $resources[] = new DiscoveredResourceData(
                    canonicalUrl: 'https://www.eprocure.gov.bd/TenderDetailsServlet?id='.$row['id'],
                    externalId: $row['id'],
                    resourceType: 'tender_notice',
                    metadata: [
                        'tender_id' => $row['id'],
                        'status' => $row['status'],
                        'procurement_nature' => 'Goods',
                        'reference_number' => 'REF-'.$row['id'],
                    ],
                );
            }

            return new DiscoveryBatch($resources, new CrawlCursor(['page' => 2, 'size' => 10]));
        }
    };

    app()->instance(SourceConnectorRegistry::class, new class($connector) extends SourceConnectorRegistry
    {
        public function __construct(private readonly SourceConnector $connector) {}

        public function for(string $type): SourceConnector
        {
            return $this->connector;
        }
    });
}

it('records what the listing said during discovery', function (): void {
    // The recorder existed, was tested, and was never called: a live crawl
    // discovered forty notices and produced no observations at all, so the
    // revision indicator had nothing to work from.
    Queue::fake();
    bindListingConnector([
        ['id' => '1315881', 'status' => 'Live'],
        ['id' => '1315880', 'status' => 'Live'],
    ]);

    app(SourceAcquisitionService::class)->discover(listingEndpoint());

    expect(TenderObservation::query()->count())->toBe(2)
        ->and(TenderObservation::query()->where('external_id', '1315881')->value('status'))->toBe('Live');
});

it('records a second observation only when the listing changed', function (): void {
    // Re-reading an unchanged listing must not accumulate identical rows, or a
    // notice would appear to be revised on every crawl.
    Queue::fake();
    $endpoint = listingEndpoint();

    bindListingConnector([['id' => '1315881', 'status' => 'Live']]);
    app(SourceAcquisitionService::class)->discover($endpoint);
    app(SourceAcquisitionService::class)->discover($endpoint->fresh());

    expect(TenderObservation::query()->count())->toBe(1);

    bindListingConnector([['id' => '1315881', 'status' => 'Archived']]);
    app(SourceAcquisitionService::class)->discover($endpoint->fresh());

    expect(TenderObservation::query()->count())->toBe(2)
        ->and(TenderObservation::query()->latest('id')->value('status'))->toBe('Archived');
});
