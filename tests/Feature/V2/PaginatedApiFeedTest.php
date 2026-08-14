<?php

use App\Contracts\Ingestion\NetworkAddressResolver;
use App\Data\Ingestion\CrawlCursor;
use App\Exceptions\Ingestion\AcquisitionFailed;
use App\Models\SourceEndpoint;
use App\Services\Ingestion\Connectors\PaginatedApiFeedConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    robotsAbsentFor('data.example');
    app()->instance(NetworkAddressResolver::class, new class implements NetworkAddressResolver
    {
        public function resolve(string $host): array
        {
            return $host === 'data.example' ? ['203.0.113.10'] : [];
        }
    });
});

function pagedEndpoint(array $options = []): SourceEndpoint
{
    return SourceEndpoint::factory()->create([
        'connector_type' => 'paginated_api',
        'base_url' => 'https://data.example/api/records?country=BD',
        'connector_options' => $options + [
            'pagination' => 'page',
            'page_parameter' => 'page',
            'size_parameter' => 'size',
            'page_size' => 2,
            'pages_per_run' => 3,
            'records_key' => 'data',
        ],
        'allowed_hosts' => ['data.example'],
        'allowed_path_prefixes' => ['/api'],
        'rate_limit_per_minute' => 60,
    ]);
}

/** A full page, then a short one, which is how a feed says it has ended. */
function pagesOfRecords(int $full): void
{
    $responses = [];

    for ($i = 0; $i < $full; $i++) {
        $responses[] = Http::response(json_encode(['data' => [['id' => 1], ['id' => 2]]]), 200, ['Content-Type' => 'application/json']);
    }

    $responses[] = Http::response(json_encode(['data' => [['id' => 9]]]), 200, ['Content-Type' => 'application/json']);

    Http::fake(['https://data.example/*' => Http::sequence($responses)]);
}

it('walks pages and stops when one comes back short', function (): void {
    // A short page is the last page. Asking for one past the end to find out
    // costs a request the publisher did not need to serve.
    pagesOfRecords(1);

    $batch = app(PaginatedApiFeedConnector::class)->discover(pagedEndpoint(), new CrawlCursor([]));

    expect($batch->resources)->toHaveCount(2)
        ->and($batch->nextCursor->toArray()['exhausted'])->toBeTrue();
});

it('archives each page as a record rather than looking for documents in it', function (): void {
    // These feeds carry records, not links. The World Bank lists 407 Bangladesh
    // projects with no file behind a row, so the page itself is the publication.
    pagesOfRecords(1);

    $batch = app(PaginatedApiFeedConnector::class)->discover(pagedEndpoint(), new CrawlCursor([]));
    $first = $batch->resources[0];

    expect($first->resourceType)->toBe('dataset')
        ->and($first->canonicalUrl)->toContain('page=1')
        ->and($first->canonicalUrl)->toContain('size=2')
        ->and($first->metadata['records'])->toBe(2);
});

it('resumes from where the last run stopped', function (): void {
    // Without this the World Bank's first twenty projects would be fetched for
    // ever and the other 387 never seen.
    pagesOfRecords(3);

    $batch = app(PaginatedApiFeedConnector::class)->discover(
        pagedEndpoint(),
        new CrawlCursor(['position' => 4, 'exhausted' => false]),
    );

    expect($batch->resources[0]->canonicalUrl)->toContain('page=4')
        ->and($batch->nextCursor->toArray()['position'])->toBeGreaterThan(4);
});

it('starts again once a feed has been exhausted, because publishers add records', function (): void {
    // A cursor parked at the end would never see anything published later.
    pagesOfRecords(3);

    $batch = app(PaginatedApiFeedConnector::class)->discover(
        pagedEndpoint(),
        new CrawlCursor(['position' => 99, 'exhausted' => true]),
    );

    expect($batch->resources[0]->canonicalUrl)->toContain('page=1');
});

it('counts offsets where a publisher paginates that way', function (): void {
    pagesOfRecords(3);

    $batch = app(PaginatedApiFeedConnector::class)->discover(
        pagedEndpoint(['pagination' => 'offset', 'page_parameter' => 'os']),
        new CrawlCursor([]),
    );

    expect($batch->resources[0]->canonicalUrl)->toContain('os=0')
        ->and($batch->resources[1]->canonicalUrl)->toContain('os=2');
});

it('keeps the pages it walked when a later one fails', function (): void {
    // One bad page part way through a walk should not throw away the pages
    // already found; they were served and they are real.
    Http::fake(['https://data.example/*' => Http::sequence([
        Http::response(json_encode(['data' => [['id' => 1], ['id' => 2]]]), 200, ['Content-Type' => 'application/json']),
        Http::response('nope', 500),
    ])]);

    $batch = app(PaginatedApiFeedConnector::class)->discover(pagedEndpoint(), new CrawlCursor([]));

    expect($batch->resources)->toHaveCount(1)
        ->and($batch->nextCursor->toArray()['exhausted'])->toBeTrue();
});

it('surfaces a failure on the first page instead of reporting an empty feed', function (): void {
    // A broken source that looks merely empty is a source nobody investigates.
    Http::fake(['https://data.example/*' => Http::response('nope', 500)]);

    expect(fn () => app(PaginatedApiFeedConnector::class)->discover(pagedEndpoint(), new CrawlCursor([])))
        ->toThrow(AcquisitionFailed::class);
});
