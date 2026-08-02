<?php

namespace App\Services\Ingestion\Connectors;

use App\Contracts\Ingestion\BrowserRenderProvider;
use App\Contracts\Ingestion\SourceConnector;
use App\Data\Ingestion\CrawlCursor;
use App\Data\Ingestion\DiscoveryBatch;
use App\Models\SourceEndpoint;
use App\Services\Ingestion\ApprovedSourceUrlGuard;
use App\Services\Ingestion\DiscoveryDocumentParser;

class BrowserSourceConnector implements SourceConnector
{
    public function __construct(
        private readonly BrowserRenderProvider $renderer,
        private readonly ApprovedSourceUrlGuard $urlGuard,
        private readonly DiscoveryDocumentParser $parser,
    ) {}

    public function discover(SourceEndpoint $endpoint, CrawlCursor $cursor): DiscoveryBatch
    {
        $baseUrl = $this->urlGuard->validate($endpoint->base_url, $endpoint)->url;
        $html = $this->renderer->render($endpoint);

        return new DiscoveryBatch(
            $this->parser->extract($html, 'text/html', $baseUrl, $endpoint),
            new CrawlCursor(['checked_at' => now()->toAtomString()]),
        );
    }
}
