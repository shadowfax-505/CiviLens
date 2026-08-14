<?php

namespace App\Services\Ingestion\Connectors;

use App\Contracts\Ingestion\SourceConnector;
use App\Data\Ingestion\CrawlCursor;
use App\Data\Ingestion\DiscoveredResourceData;
use App\Data\Ingestion\DiscoveryBatch;
use App\Models\SourceEndpoint;
use App\Services\Ingestion\SafeHttpTransport;
use Throwable;

/**
 * Walk a paginated API and archive each page as a published record.
 *
 * `ApiFeedSourceConnector` fetches one URL and stops, so a paginated publisher
 * yields its first page for ever — the World Bank lists 407 Bangladesh projects
 * and we would have seen the first twenty of them, indefinitely.
 *
 * These feeds carry records rather than links to documents, so there is nothing
 * for the discovery parser to find in them. The page itself is the publication:
 * each page URL is emitted as a resource and archived by the ordinary fetch
 * path, which gives it a hash, a version and a provenance record like any other
 * document.
 *
 * That means each page is fetched twice — once here to see whether it is the
 * last one, once by the fetch job to store it. Discovery and acquisition are
 * deliberately separate, and paying one extra request per page is a smaller
 * price than a connector that stores artifacts behind the acquisition pipeline's
 * back.
 */
class PaginatedApiFeedConnector implements SourceConnector
{
    public function discover(SourceEndpoint $endpoint, CrawlCursor $cursor): DiscoveryBatch
    {
        $options = $endpoint->connectorOptions();

        $pageParameter = (string) ($options['page_parameter'] ?? 'page');
        $sizeParameter = (string) ($options['size_parameter'] ?? 'size');
        $pageSize = max(1, (int) ($options['page_size'] ?? 50));
        $pagesPerRun = max(1, (int) ($options['pages_per_run'] ?? 5));
        $countsOffsets = ($options['pagination'] ?? 'page') === 'offset';
        $recordsKey = isset($options['records_key']) ? (string) $options['records_key'] : null;

        $state = $cursor->toArray();
        $position = (int) ($state['position'] ?? ($countsOffsets ? 0 : 1));
        $exhausted = (bool) ($state['exhausted'] ?? false);

        // A feed that has run out is checked again from the beginning rather
        // than never again: publishers add records, and a cursor parked at the
        // end would never see them.
        if ($exhausted) {
            $position = $countsOffsets ? 0 : 1;
        }

        $resources = [];
        $fetched = 0;

        while ($fetched < $pagesPerRun) {
            $url = $this->pageUrl($endpoint->base_url, $pageParameter, $sizeParameter, $position, $pageSize);

            try {
                $response = $this->transport->get($url, $endpoint, (int) config('civiclens.ingestion.discovery_max_content_bytes', 5242880));
            } catch (Throwable $exception) {
                // A failure on the first page of a run is the endpoint failing,
                // and hiding it would leave a broken source looking merely
                // empty. A failure part way through is one bad page: the pages
                // already walked are real and worth keeping.
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

            $records = $this->countRecords($response->content, $recordsKey);

            if ($records > 0) {
                $resources[] = new DiscoveredResourceData(
                    canonicalUrl: $url,
                    discoveryUrl: $endpoint->base_url,
                    externalId: 'page-'.$position,
                    // Archived as published data rather than treated as a
                    // document to be read: nothing here is scanned or OCR'd.
                    resourceType: 'dataset',
                    metadata: ['records' => $records, 'page' => $position],
                );
            }

            $fetched++;
            $position += $countsOffsets ? $pageSize : 1;

            // A short page is the last page. Publishers rarely say how many
            // there are, and asking for one past the end to find out costs a
            // request the publisher did not need to serve.
            if ($records < $pageSize) {
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

    public function __construct(private readonly SafeHttpTransport $transport) {}

    private function pageUrl(string $baseUrl, string $pageParameter, string $sizeParameter, int $position, int $pageSize): string
    {
        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl.$separator.http_build_query([
            $pageParameter => $position,
            $sizeParameter => $pageSize,
        ]);
    }

    /**
     * How many records a page holds, so the last one can be recognised.
     *
     * Named explicitly where a publisher's shape is known; otherwise the largest
     * array anywhere in the payload, which is the record list in every feed
     * looked at and is at worst an overcount that costs one extra page.
     */
    private function countRecords(string $body, ?string $recordsKey): int
    {
        try {
            $payload = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return 0;
        }

        if (! is_array($payload)) {
            return 0;
        }

        if ($recordsKey !== null && $recordsKey !== '') {
            $records = data_get($payload, $recordsKey);

            return is_array($records) ? count($records) : 0;
        }

        return $this->largestArray($payload);
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function largestArray(array $payload, int $depth = 0): int
    {
        $largest = array_is_list($payload) ? count($payload) : 0;

        if ($depth >= 3) {
            return $largest;
        }

        foreach ($payload as $value) {
            if (is_array($value)) {
                $largest = max($largest, $this->largestArray($value, $depth + 1));
            }
        }

        return $largest;
    }
}
