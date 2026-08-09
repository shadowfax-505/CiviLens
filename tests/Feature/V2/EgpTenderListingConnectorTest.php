<?php

use App\Contracts\Ingestion\NetworkAddressResolver;
use App\Data\Ingestion\CrawlCursor;
use App\Exceptions\Ingestion\AcquisitionFailed;
use App\Exceptions\Ingestion\UnsafeSourceUrl;
use App\Models\SourceEndpoint;
use App\Services\Ingestion\Connectors\EgpTenderListingConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/** @param array<string, list<string>> $addressesByHost */
function bindEgpAddresses(array $addressesByHost): void
{
    app()->instance(NetworkAddressResolver::class, new class($addressesByHost) implements NetworkAddressResolver
    {
        /** @param array<string, list<string>> $addressesByHost */
        public function __construct(private readonly array $addressesByHost) {}

        public function resolve(string $host): array
        {
            return $this->addressesByHost[$host] ?? [];
        }
    });
}

beforeEach(fn () => bindEgpAddresses(['data.example' => ['93.184.216.34']]));

function egpEndpoint(): SourceEndpoint
{
    return SourceEndpoint::factory()->make([
        'base_url' => 'https://data.example/TenderDetailsServlet',
        'allowed_hosts' => ['data.example'],
        'allowed_path_prefixes' => ['/TenderDetailsServlet'],
    ]);
}

function egpRows(): string
{
    return (string) file_get_contents(__DIR__.'/../../Fixtures/Ingestion/egp-tender-rows.html');
}

it('discovers tender notices from a real listing response', function (): void {
    Http::fake(['https://data.example/TenderDetailsServlet' => Http::response(egpRows(), 200, ['Content-Type' => 'text/html'])]);

    $batch = app(EgpTenderListingConnector::class)->discover(egpEndpoint(), new CrawlCursor);

    expect($batch->resources)->toHaveCount(10)
        ->and($batch->resources[0]->externalId)->toBe('1316153')
        ->and($batch->resources[0]->resourceType)->toBe('tender_notice')
        ->and($batch->resources[0]->metadata['status'])->toBe('Live')
        ->and($batch->nextCursor->toArray()['page'])->toBe(2);
});

it('posts the pagination the servlet expects', function (): void {
    Http::fake(['https://data.example/TenderDetailsServlet' => Http::response(egpRows(), 200, ['Content-Type' => 'text/html'])]);

    app(EgpTenderListingConnector::class)->discover(egpEndpoint(), new CrawlCursor(['page' => 4, 'size' => 25]));

    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
        && str_contains($request->body(), 'pageNo=4')
        && str_contains($request->body(), 'size=25')
        && str_contains($request->body(), 'funName=AllTenders'));
});

it('advances one page per run rather than walking the archive at once', function (): void {
    // A publisher that has not asked to be crawled quickly should not be.
    Http::fake(['https://data.example/TenderDetailsServlet' => Http::response(egpRows(), 200, ['Content-Type' => 'text/html'])]);

    $batch = app(EgpTenderListingConnector::class)->discover(egpEndpoint(), new CrawlCursor(['page' => 7, 'size' => 10]));

    expect($batch->nextCursor->toArray()['page'])->toBe(8);
    Http::assertSentCount(1);
});

it('refuses to advance past a placeholder page', function (): void {
    // The failure this guards: a 200 carrying template text would otherwise
    // advance the cursor, walking the crawl past real pages while reporting
    // success.
    Http::fake(['https://data.example/TenderDetailsServlet' => Http::response(
        '<html><body>'.str_repeat('Lorem ipsum dolor sit amet. ', 20).'</body></html>',
        200,
        ['Content-Type' => 'text/html'],
    )]);

    expect(fn () => app(EgpTenderListingConnector::class)->discover(egpEndpoint(), new CrawlCursor))
        ->toThrow(AcquisitionFailed::class, 'usable content');
});

it('holds the cursor at an exhausted page instead of skipping past it', function (): void {
    // An empty page is the end of the listing, not a failure. Re-checking the
    // same page next run finds notices published since.
    Http::fake(['https://data.example/TenderDetailsServlet' => Http::response('<table></table>', 200, ['Content-Type' => 'text/html'])]);

    $batch = app(EgpTenderListingConnector::class)->discover(egpEndpoint(), new CrawlCursor(['page' => 5, 'size' => 10]));

    expect($batch->resources)->toBe([])
        ->and($batch->nextCursor->toArray()['page'])->toBe(5)
        ->and($batch->nextCursor->toArray()['exhausted'])->toBeTrue();
});

it('bounds page size by the configured discovery cap', function (): void {
    config()->set('civiclens.ingestion.max_discovered_per_run', 20);
    Http::fake(['https://data.example/TenderDetailsServlet' => Http::response(egpRows(), 200, ['Content-Type' => 'text/html'])]);

    app(EgpTenderListingConnector::class)->discover(egpEndpoint(), new CrawlCursor(['page' => 1, 'size' => 5000]));

    Http::assertSent(fn (ClientRequest $request): bool => str_contains($request->body(), 'size=20'));
});

it('refuses a listing served from a host outside the allowlist', function (): void {
    bindEgpAddresses(['data.example' => ['93.184.216.34'], 'other.example' => ['93.184.216.34']]);
    $connector = app(EgpTenderListingConnector::class);
    $endpoint = SourceEndpoint::factory()->make([
        'base_url' => 'https://other.example/TenderDetailsServlet',
        'allowed_hosts' => ['data.example'],
        'allowed_path_prefixes' => ['/TenderDetailsServlet'],
    ]);

    expect(fn () => $connector->discover($endpoint, new CrawlCursor))
        ->toThrow(UnsafeSourceUrl::class);
    Http::assertNothingSent();
});

it('rejects a malformed cursor rather than trusting it', function (): void {
    Http::fake(['https://data.example/TenderDetailsServlet' => Http::response(egpRows(), 200, ['Content-Type' => 'text/html'])]);

    app(EgpTenderListingConnector::class)->discover(egpEndpoint(), new CrawlCursor(['page' => -5, 'size' => 'lots']));

    Http::assertSent(fn (ClientRequest $request): bool => str_contains($request->body(), 'pageNo=1')
        && str_contains($request->body(), 'size=10'));
});
