<?php

use App\Contracts\Ingestion\NetworkAddressResolver;
use App\Data\Ingestion\CrawlCursor;
use App\Models\SourceEndpoint;
use App\Services\Ingestion\Connectors\PaginatedHtmlListingConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    robotsAbsentFor('court.example');
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
