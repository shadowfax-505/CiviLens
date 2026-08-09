<?php

namespace App\Services\Ingestion\Connectors;

use App\Contracts\Ingestion\SourceConnector;
use App\Data\Ingestion\CrawlCursor;
use App\Data\Ingestion\DiscoveredResourceData;
use App\Data\Ingestion\DiscoveryBatch;
use App\Data\Ingestion\TenderListingRow;
use App\Exceptions\Ingestion\AcquisitionFailed;
use App\Models\SourceEndpoint;
use App\Services\Ingestion\SafeHttpTransport;
use App\Services\Ingestion\SourceContentValidator;
use App\Services\Ingestion\TenderListingParser;

/**
 * Discover public tender notices from the e-GP listing servlet.
 *
 * The servlet takes pagination as a form body rather than a URL, so this is the
 * one connector that posts. Everything else is unchanged: the same allowlist,
 * the same fail-closed address pinning, the same byte ceiling.
 *
 * One page per run. The listing is paginated and the cursor advances a page at
 * a time, so a crawl spreads across scheduled runs at the endpoint's rate limit
 * instead of walking the whole archive in one burst. A publisher that has not
 * asked to be crawled quickly should not be crawled quickly.
 */
class EgpTenderListingConnector implements SourceConnector
{
    private const DEFAULT_PAGE_SIZE = 10;

    public function __construct(
        private readonly SafeHttpTransport $transport,
        private readonly TenderListingParser $parser,
        private readonly SourceContentValidator $validator,
    ) {}

    public function discover(SourceEndpoint $endpoint, CrawlCursor $cursor): DiscoveryBatch
    {
        $page = $this->page($cursor);
        $size = $this->size($cursor);

        $response = $this->transport->post(
            $endpoint->base_url,
            $endpoint,
            [
                'funName' => 'AllTenders',
                'keyword' => '',
                'pageNo' => $page,
                'size' => $size,
                'homeWSearch' => 'homeWSearch',
                'approve' => 'false',
                'h' => 't',
            ],
            (int) config('civiclens.ingestion.discovery_max_content_bytes', 5242880),
        );

        // A 200 carrying a placeholder or an error page must not advance the
        // cursor, or the crawl walks past real pages while reporting success.
        $validation = $this->validator->validate($response->content, $response->mediaType);

        if (! $validation['usable']) {
            throw new AcquisitionFailed('Source listing did not carry usable content: '.implode('; ', $validation['reasons']));
        }

        $rows = $this->parser->parse($response->content);

        if ($rows === []) {
            // An empty page is the end of the listing, not a failure. Hold the
            // cursor so the next run re-checks the same page for new notices
            // rather than skipping past them.
            return new DiscoveryBatch([], new CrawlCursor(['page' => $page, 'size' => $size, 'exhausted' => true]));
        }

        return new DiscoveryBatch(
            array_map(fn (TenderListingRow $row): DiscoveredResourceData => $this->resource($endpoint, $row), $rows),
            new CrawlCursor(['page' => $page + 1, 'size' => $size, 'exhausted' => false]),
        );
    }

    private function resource(SourceEndpoint $endpoint, TenderListingRow $row): DiscoveredResourceData
    {
        return new DiscoveredResourceData(
            canonicalUrl: rtrim($endpoint->base_url, '/').'?tenderId='.rawurlencode($row->tenderId),
            discoveryUrl: $endpoint->base_url,
            externalId: $row->tenderId,
            resourceType: 'tender_notice',
            metadata: $row->toArray(),
        );
    }

    private function page(CrawlCursor $cursor): int
    {
        $page = $cursor->toArray()['page'] ?? 1;

        return is_int($page) && $page > 0 ? $page : 1;
    }

    private function size(CrawlCursor $cursor): int
    {
        $size = $cursor->toArray()['size'] ?? self::DEFAULT_PAGE_SIZE;
        $max = max(1, (int) config('civiclens.ingestion.max_discovered_per_run', 250));

        return is_int($size) && $size > 0 ? min($size, $max) : self::DEFAULT_PAGE_SIZE;
    }
}
