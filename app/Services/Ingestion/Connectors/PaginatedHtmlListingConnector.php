<?php

namespace App\Services\Ingestion\Connectors;

use App\Contracts\Ingestion\SourceConnector;
use App\Data\Ingestion\CrawlCursor;
use App\Data\Ingestion\DiscoveryBatch;
use App\Models\SourceEndpoint;
use App\Services\Ingestion\DiscoveryDocumentParser;
use App\Services\Ingestion\SafeHttpTransport;
use Throwable;

/**
 * Walk an HTML listing that pages by an offset in its query string.
 *
 * The static connector fetches one page, which is right for an archive page
 * that carries every link it has. The Supreme Court's judgment listing is not
 * that: it serves fifty documents at a time and moves through them with
 * `&start=`, so a single fetch reaches fifty of however many thousand exist and
 * reports itself as complete.
 *
 * Distinct from PaginatedApiFeedConnector, which archives each page *as* a
 * record because the page is the publication. Here the page is an index and the
 * documents behind it are the publication, so each page is parsed and its links
 * emitted.
 *
 * Two things this connector does not assume:
 *
 * - **That a search form is the only way in.** The judgments page carries a
 *   POST form and an AJAX search, and the same listing is reachable by GET —
 *   the pagination handler builds a plain URL. Reading the form and concluding
 *   the source needs POST would have registered it as unreachable, which is
 *   what happened to e-GP's detail servlet for a real reason and would have
 *   happened here for no reason at all.
 * - **That the run finishes.** `pages_per_run` bounds a single crawl so a
 *   publisher rate-limited to a request a minute is not held for hours, and the
 *   cursor carries the offset so the next run continues rather than restarting.
 */
class PaginatedHtmlListingConnector implements SourceConnector
{
    public function __construct(
        private readonly SafeHttpTransport $transport,
        private readonly DiscoveryDocumentParser $parser,
    ) {}

    public function discover(SourceEndpoint $endpoint, CrawlCursor $cursor): DiscoveryBatch
    {
        $options = $endpoint->connectorOptions();

        $offsetParameter = (string) ($options['offset_parameter'] ?? 'start');
        $pageSize = max(1, (int) ($options['page_size'] ?? 50));
        $pagesPerRun = max(1, (int) ($options['pages_per_run'] ?? 5));

        $state = $cursor->toArray();
        $position = max(0, (int) ($state['position'] ?? 0));
        $exhausted = (bool) ($state['exhausted'] ?? false);

        // A listing that has run out is walked again from the beginning rather
        // than never again. Courts publish judgments continuously, and the new
        // ones appear at the front.
        if ($exhausted) {
            $position = 0;
        }

        $resources = [];
        $seen = [];
        $fetched = 0;

        while ($fetched < $pagesPerRun) {
            $url = $this->pageUrl($endpoint->base_url, $offsetParameter, $position);

            try {
                $response = $this->transport->get(
                    $url,
                    $endpoint,
                    (int) config('civiclens.ingestion.discovery_max_content_bytes', 5242880),
                );
            } catch (Throwable $exception) {
                // A failure on the first page of a run is the endpoint failing,
                // and swallowing it would leave a broken source looking merely
                // empty. A failure part way through is one bad page, and the
                // pages already walked are real.
                if ($resources === []) {
                    throw $exception;
                }

                $exhausted = true;

                break;
            }

            if ($response->status !== 200) {
                $exhausted = true;

                break;
            }

            $found = $this->parser->extract($response->content, 'text/html', $response->url, $endpoint);
            $new = 0;

            foreach ($found as $resource) {
                if (isset($seen[$resource->canonicalUrl])) {
                    continue;
                }

                $seen[$resource->canonicalUrl] = true;
                $resources[] = $resource;
                $new++;
            }

            $fetched++;
            $position += $pageSize;

            // A page past the end serves the listing's furniture and no new
            // documents. Stopping on that rather than on a declared total,
            // because the listing does not declare one — and an offset walked
            // past the end returns 200 with the same navigation on it, so a
            // status check alone would walk for ever.
            if ($new === 0) {
                $exhausted = true;

                break;
            }
        }

        return new DiscoveryBatch($resources, new CrawlCursor([
            'position' => $position,
            'exhausted' => $exhausted,
            'checked_at' => now()->toAtomString(),
        ]));
    }

    private function pageUrl(string $baseUrl, string $offsetParameter, int $position): string
    {
        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl.$separator.http_build_query([$offsetParameter => $position]);
    }
}
