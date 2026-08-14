<?php

namespace App\Services\Ingestion;

use App\Contracts\Ingestion\SourceConnector;
use App\Exceptions\Ingestion\AcquisitionFailed;
use App\Services\Ingestion\Connectors\ApiFeedSourceConnector;
use App\Services\Ingestion\Connectors\BrowserSourceConnector;
use App\Services\Ingestion\Connectors\DirectDownloadSourceConnector;
use App\Services\Ingestion\Connectors\EgpTenderListingConnector;
use App\Services\Ingestion\Connectors\PaginatedApiFeedConnector;
use App\Services\Ingestion\Connectors\SitemapSourceConnector;
use App\Services\Ingestion\Connectors\StaticHtmlSourceConnector;

class SourceConnectorRegistry
{
    public function __construct(
        private readonly ApiFeedSourceConnector $apiFeed,
        private readonly SitemapSourceConnector $sitemap,
        private readonly DirectDownloadSourceConnector $directDownload,
        private readonly StaticHtmlSourceConnector $staticHtml,
        private readonly BrowserSourceConnector $browser,
        private readonly EgpTenderListingConnector $egpTenderListing,
        private readonly PaginatedApiFeedConnector $paginatedApiFeed,
    ) {}

    public function for(string $type): SourceConnector
    {
        return match ($type) {
            'api', 'feed' => $this->apiFeed,
            'paginated_api' => $this->paginatedApiFeed,
            'sitemap' => $this->sitemap,
            'direct_download' => $this->directDownload,
            'static_html' => $this->staticHtml,
            'browser' => $this->browser,
            // The e-GP listing servlet answers POST and nothing else, so it
            // needs the one connector that posts. Routing it through
            // static_html would issue a GET and discover no notices at all.
            'egp_tender_listing' => $this->egpTenderListing,
            default => throw new AcquisitionFailed('Unsupported source connector type.'),
        };
    }
}
