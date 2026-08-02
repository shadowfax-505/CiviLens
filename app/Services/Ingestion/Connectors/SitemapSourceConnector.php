<?php

namespace App\Services\Ingestion\Connectors;

use App\Contracts\Ingestion\SourceConnector;
use App\Data\Ingestion\CrawlCursor;
use App\Data\Ingestion\DiscoveryBatch;
use App\Models\SourceEndpoint;
use App\Services\Ingestion\DiscoveryDocumentParser;
use App\Services\Ingestion\SafeHttpTransport;

class SitemapSourceConnector implements SourceConnector
{
    public function __construct(
        private readonly SafeHttpTransport $transport,
        private readonly DiscoveryDocumentParser $parser,
    ) {}

    public function discover(SourceEndpoint $endpoint, CrawlCursor $cursor): DiscoveryBatch
    {
        $response = $this->transport->get(
            $endpoint->base_url,
            $endpoint,
            (int) config('civiclens.ingestion.discovery_max_content_bytes', 5242880),
            $cursor->conditionalHeaders(),
        );

        if ($response->notModified()) {
            return new DiscoveryBatch([], $cursor);
        }

        return new DiscoveryBatch(
            $this->parser->extract($response->content, $response->mediaType, $response->url, $endpoint),
            new CrawlCursor([
                'last_modified' => $response->headers['Last-Modified'][0] ?? $response->headers['last-modified'][0] ?? null,
                'checked_at' => now()->toAtomString(),
            ]),
        );
    }
}
