<?php

namespace App\Services\Ingestion;

use App\Contracts\Ingestion\SourceConnector;
use App\Exceptions\Ingestion\AcquisitionFailed;
use App\Services\Ingestion\Connectors\ApiFeedSourceConnector;
use App\Services\Ingestion\Connectors\BrowserSourceConnector;
use App\Services\Ingestion\Connectors\DirectDownloadSourceConnector;
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
    ) {}

    public function for(string $type): SourceConnector
    {
        return match ($type) {
            'api', 'feed' => $this->apiFeed,
            'sitemap' => $this->sitemap,
            'direct_download' => $this->directDownload,
            'static_html' => $this->staticHtml,
            'browser' => $this->browser,
            default => throw new AcquisitionFailed('Unsupported source connector type.'),
        };
    }
}
