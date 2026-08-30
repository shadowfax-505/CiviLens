<?php

use App\Contracts\Ingestion\NetworkAddressResolver;
use App\Data\Ingestion\CrawlCursor;
use App\Models\SourceEndpoint;
use App\Services\Ingestion\Connectors\PaginatedHtmlListingConnector;
use App\Services\Ingestion\DiscoveryPacing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/** Records the waits instead of taking them. */
function recordingPacing(array &$waits): DiscoveryPacing
{
    return new class($waits) extends DiscoveryPacing
    {
        /** @param list<int> $waits */
        public function __construct(private array &$waits) {}

        public function wait(int $seconds): void
        {
            $this->waits[] = $seconds;
        }
    };
}

beforeEach(function (): void {
    robotsAbsentFor('court.example');
    // Nothing in the suite should spend wall-clock seconds pacing.
    app()->instance(DiscoveryPacing::class, new class extends DiscoveryPacing
    {
        public function wait(int $seconds): void {}
    });
    app()->instance(NetworkAddressResolver::class, new class implements NetworkAddressResolver
    {
        public function resolve(string $host): array
        {
            return $host === 'court.example' ? ['203.0.113.20'] : [];
        }
    });
});

function judgmentListingEndpoint(array $options = []): SourceEndpoint
{
    return SourceEndpoint::factory()->create([
        'connector_type' => 'paginated_html_listing',
        'base_url' => 'https://court.example/web/?page=judgments.php&div_id=2',
        'connector_options' => $options + [
            'offset_parameter' => 'start',
            'page_size' => 2,
            'pages_per_run' => 5,
        ],
        'allowed_hosts' => ['court.example'],
        'allowed_path_prefixes' => ['/web', '/resources'],
        'rate_limit_per_minute' => 60,
    ]);
}

/** A listing page carrying links to judgment PDFs, plus its own navigation. */
function judgmentListingPage(array $documents): string
{
    $links = '<a href="/web/?page=judgments.php&div_id=2">Judgments</a>';

    foreach ($documents as $name) {
        $links .= '<a href="/resources/documents/'.$name.'.pdf">'.$name.'</a>';
    }

    return '<html><body>'.$links.'</body></html>';
}

it('walks the offsets instead of stopping at the first page', function (): void {
    // The static connector fetches one page. This listing serves fifty
    // documents at a time, so a single fetch reaches fifty of several thousand
    // and reports itself complete — which is the quietest way a corpus stays
    // small.
    Http::fake([
        'https://court.example/web/*start=0*' => Http::response(judgmentListingPage(['WP_1_20', 'WP_2_20']), 200),
        'https://court.example/web/*start=2*' => Http::response(judgmentListingPage(['CR_3_19', 'CR_4_19']), 200),
        // Past the end: the same page, with its navigation and no documents.
        'https://court.example/web/*start=4*' => Http::response(judgmentListingPage([]), 200),
    ]);

    $batch = app(PaginatedHtmlListingConnector::class)->discover(judgmentListingEndpoint(), new CrawlCursor);

    $urls = array_map(fn ($resource): string => $resource->canonicalUrl, $batch->resources);

    expect($urls)->toContain('https://court.example/resources/documents/WP_1_20.pdf')
        ->and($urls)->toContain('https://court.example/resources/documents/CR_4_19.pdf');
});

it('stops when a page yields nothing new rather than walking for ever', function (): void {
    // An offset past the end returns 200 with the listing's own navigation on
    // it, so a status check alone never terminates. Only "no new documents"
    // distinguishes the end.
    Http::fake([
        'https://court.example/web/*start=0*' => Http::response(judgmentListingPage(['WP_1_20']), 200),
        'https://court.example/web/*' => Http::response(judgmentListingPage([]), 200),
    ]);

    $batch = app(PaginatedHtmlListingConnector::class)->discover(judgmentListingEndpoint(), new CrawlCursor);

    expect($batch->nextCursor->toArray()['exhausted'])->toBeTrue();
});

it('continues from where the last run stopped', function (): void {
    // A request a minute against the court's host means a run is bounded and
    // the next one has to pick up the offset rather than restart.
    Http::fake([
        'https://court.example/web/*start=4*' => Http::response(judgmentListingPage(['WP_9_18']), 200),
        'https://court.example/web/*' => Http::response(judgmentListingPage([]), 200),
    ]);

    $batch = app(PaginatedHtmlListingConnector::class)->discover(
        judgmentListingEndpoint(['pages_per_run' => 1]),
        new CrawlCursor(['position' => 4, 'exhausted' => false]),
    );

    $urls = array_map(fn ($resource): string => $resource->canonicalUrl, $batch->resources);

    expect($urls)->toContain('https://court.example/resources/documents/WP_9_18.pdf')
        ->and($batch->nextCursor->toArray()['position'])->toBe(6);
});

it('walks a finished listing again from the start, because courts keep publishing', function (): void {
    Http::fake([
        'https://court.example/web/*start=0*' => Http::response(judgmentListingPage(['WP_NEW_26']), 200),
        'https://court.example/web/*' => Http::response(judgmentListingPage([]), 200),
    ]);

    $batch = app(PaginatedHtmlListingConnector::class)->discover(
        judgmentListingEndpoint(['pages_per_run' => 1]),
        new CrawlCursor(['position' => 5000, 'exhausted' => true]),
    );

    $urls = array_map(fn ($resource): string => $resource->canonicalUrl, $batch->resources);

    expect($urls)->toContain('https://court.example/resources/documents/WP_NEW_26.pdf');
});

it('paces itself at the rate the endpoint states', function (): void {
    // The `source-fetch` limiter is registered against
    // FetchDiscoveredResourceArtifact — the fetch stage. A connector that
    // issues one request per crawl never noticed. This one walks pages in a
    // loop, and on its first real run it put fifty requests to the court's host
    // inside a minute against a stated limit of one. The host kept answering,
    // which was luck: bgpress stopped answering at four a minute and now
    // refuses 443 outright.
    Http::fake([
        'https://court.example/web/*start=0*' => Http::response(judgmentListingPage(['WP_1_20', 'WP_2_20']), 200),
        'https://court.example/web/*start=2*' => Http::response(judgmentListingPage(['CR_3_19', 'CR_4_19']), 200),
        'https://court.example/web/*' => Http::response(judgmentListingPage([]), 200),
    ]);

    $waits = [];
    app()->instance(DiscoveryPacing::class, recordingPacing($waits));

    $endpoint = judgmentListingEndpoint();
    $endpoint->forceFill(['rate_limit_per_minute' => 1])->save();

    app(PaginatedHtmlListingConnector::class)->discover($endpoint, new CrawlCursor);

    // Three pages fetched, so two intervals, one minute each. Not before the
    // first: pacing the opening request of a run delays every crawl for nothing.
    expect($waits)->toBe([60, 60]);
});

it('paces faster where the endpoint allows more', function (): void {
    Http::fake([
        'https://court.example/web/*start=0*' => Http::response(judgmentListingPage(['WP_1_20', 'WP_2_20']), 200),
        'https://court.example/web/*' => Http::response(judgmentListingPage([]), 200),
    ]);

    $waits = [];
    app()->instance(DiscoveryPacing::class, recordingPacing($waits));

    $endpoint = judgmentListingEndpoint();
    $endpoint->forceFill(['rate_limit_per_minute' => 6])->save();

    app(PaginatedHtmlListingConnector::class)->discover($endpoint, new CrawlCursor);

    expect($waits)->toBe([10]);
});
