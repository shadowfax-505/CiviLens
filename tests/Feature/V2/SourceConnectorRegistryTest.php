<?php

use App\Services\Ingestion\BangladeshSourceCatalogue;
use App\Services\Ingestion\Connectors\ApiFeedSourceConnector;
use App\Services\Ingestion\Connectors\BrowserSourceConnector;
use App\Services\Ingestion\Connectors\DirectDownloadSourceConnector;
use App\Services\Ingestion\Connectors\EgpTenderListingConnector;
use App\Services\Ingestion\Connectors\SitemapSourceConnector;
use App\Services\Ingestion\Connectors\StaticHtmlSourceConnector;
use App\Services\Ingestion\SourceConnectorRegistry;

it('routes the e-GP listing type to the connector that can post', function (): void {
    // The e-GP listing lives behind a servlet that answers POST and nothing
    // else. A connector that issues a GET does not fetch fewer notices, it
    // fetches none, so the type has to reach the one implementation that posts.
    expect(app(SourceConnectorRegistry::class)->for('egp_tender_listing'))
        ->toBeInstanceOf(EgpTenderListingConnector::class);
});

it('leaves every other connector type on its existing implementation', function (): void {
    // Adding an arm to the match must not quietly reroute an endpoint that was
    // already crawling correctly.
    $registry = app(SourceConnectorRegistry::class);

    expect($registry->for('api'))->toBeInstanceOf(ApiFeedSourceConnector::class)
        ->and($registry->for('feed'))->toBeInstanceOf(ApiFeedSourceConnector::class)
        ->and($registry->for('sitemap'))->toBeInstanceOf(SitemapSourceConnector::class)
        ->and($registry->for('direct_download'))->toBeInstanceOf(DirectDownloadSourceConnector::class)
        ->and($registry->for('static_html'))->toBeInstanceOf(StaticHtmlSourceConnector::class)
        ->and($registry->for('browser'))->toBeInstanceOf(BrowserSourceConnector::class);
});

it('declares a resolvable connector type for every catalogued endpoint', function (): void {
    // A registration that names a connector type nothing implements fails at
    // the first crawl rather than at registration, which is the worst place to
    // find out. Catalogue and registry are checked against each other here.
    $registry = app(SourceConnectorRegistry::class);
    $checked = 0;

    foreach (app(BangladeshSourceCatalogue::class)->definitions() as $publisher) {
        foreach ($publisher['endpoints'] as $endpoint) {
            // for() throws on an unimplemented type, which is the failure this
            // guards against.
            $registry->for($endpoint['connector_type']);
            $checked++;
        }
    }

    expect($checked)->toBeGreaterThan(0);
});

it('points the e-GP servlet endpoint at the posting connector', function (): void {
    // The end-to-end wiring: what the catalogue declares for the servlet has to
    // resolve to the posting connector, not merely to something that exists.
    $registry = app(SourceConnectorRegistry::class);
    $servlet = null;

    foreach (app(BangladeshSourceCatalogue::class)->definitions() as $publisher) {
        foreach ($publisher['endpoints'] as $endpoint) {
            if (str_contains($endpoint['base_url'], 'TenderDetailsServlet')) {
                $servlet = $endpoint;
            }
        }
    }

    expect($servlet)->not->toBeNull()
        ->and($registry->for($servlet['connector_type']))->toBeInstanceOf(EgpTenderListingConnector::class);
});
