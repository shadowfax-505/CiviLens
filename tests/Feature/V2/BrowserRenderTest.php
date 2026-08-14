<?php

use App\Contracts\Ingestion\BrowserRenderProvider;
use App\Contracts\Ingestion\NetworkAddressResolver;
use App\Data\Ingestion\CrawlCursor;
use App\Exceptions\Ingestion\AcquisitionFailed;
use App\Exceptions\Ingestion\UnsafeSourceUrl;
use App\Models\SourceEndpoint;
use App\Services\Ingestion\Connectors\BrowserSourceConnector;
use App\Services\Ingestion\PlaywrightBrowserRenderProvider;
use App\Services\Ingestion\UnavailableBrowserRenderProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    robotsAbsentFor('data.example', 'files.example');
    // Both hosts resolve: a rendered page's documents commonly sit on another
    // host entirely — IMED's PDFs live in Oracle object storage, not on
    // imed.gov.bd — and a fixture that resolved only the page's host would
    // quietly drop exactly the case this connector exists for.
    app()->instance(NetworkAddressResolver::class, new class implements NetworkAddressResolver
    {
        public function resolve(string $host): array
        {
            return in_array($host, ['data.example', 'files.example'], true) ? ['203.0.113.10'] : [];
        }
    });
});

function browserEndpoint(): SourceEndpoint
{
    return SourceEndpoint::factory()->create([
        'connector_type' => 'browser',
        'base_url' => 'https://data.example/reports',
        'allowed_hosts' => ['data.example', 'files.example'],
        'allowed_path_prefixes' => ['/reports', '/files'],
        'rate_limit_per_minute' => 60,
        'max_content_bytes' => 5242880,
    ]);
}

it('finds documents that only exist once the page has run', function (): void {
    // IMED's annual reports page carries forty-four links and no documents to
    // the HTTP transport; the PDFs appear only after its JavaScript runs, and no
    // amount of guessing URLs would have found them.
    app()->instance(BrowserRenderProvider::class, new class implements BrowserRenderProvider
    {
        public function render(SourceEndpoint $endpoint): string
        {
            return '<a href="https://files.example/files/annual-report-2025.pdf">Annual report</a>';
        }
    });

    $batch = app(BrowserSourceConnector::class)->discover(browserEndpoint(), new CrawlCursor([]));

    expect($batch->resources)->toHaveCount(1)
        ->and($batch->resources[0]->canonicalUrl)->toBe('https://files.example/files/annual-report-2025.pdf');
});

it('fails closed when no browser is configured', function (): void {
    // The default on a machine that has not opted in. A renderer that silently
    // returned nothing would make a publisher look empty instead of unread.
    app()->instance(BrowserRenderProvider::class, new UnavailableBrowserRenderProvider);

    expect(fn () => app(BrowserSourceConnector::class)->discover(browserEndpoint(), new CrawlCursor([])))
        ->toThrow(AcquisitionFailed::class, 'not configured');
});

it('refuses to render for an endpoint with no allowlisted host', function (): void {
    // The worker enforces the allowlist per request, so an empty allowlist would
    // mean enforcing nothing at all.
    config(['civiclens.ingestion.browser.node' => '/bin/sh']);

    $endpoint = browserEndpoint();
    $endpoint->forceFill(['allowed_hosts' => []])->save();

    expect(fn () => app(PlaywrightBrowserRenderProvider::class)->render($endpoint))
        ->toThrow(AcquisitionFailed::class, 'no allowlisted host');
});

it('checks robots before launching a browser at all', function (): void {
    // The worker does not speak to the transport, so this is the only place a
    // publisher's rules can reach a rendered fetch. A renderer that ignored
    // robots would be a way round the rule rather than an exception to it.
    robotsFor('data.example', "User-agent: *\nDisallow: /reports\n");
    config(['civiclens.ingestion.browser.node' => '/bin/sh']);

    expect(fn () => app(PlaywrightBrowserRenderProvider::class)->render(browserEndpoint()))
        ->toThrow(UnsafeSourceUrl::class, 'Disallow: /reports');
});

it('reports an unconfigured node binary rather than pretending to render', function (): void {
    config(['civiclens.ingestion.browser.node' => '']);

    expect(fn () => app(PlaywrightBrowserRenderProvider::class)->render(browserEndpoint()))
        ->toThrow(AcquisitionFailed::class, 'not configured');
});
