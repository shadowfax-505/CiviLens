<?php

namespace App\Services\Ingestion;

/**
 * The reviewed set of Bangladesh public sources CivicLens may fetch from.
 *
 * These declarations used to sit inside a console closure, where nothing could
 * reach them. A registration that names a connector type no implementation
 * provides is only discovered at the first crawl otherwise, which is the worst
 * moment to find out. Holding them here lets the catalogue and the connector
 * registry be checked against each other by a test.
 *
 * Recording an entry does not start a crawl. Every endpoint is created paused
 * and resuming one is a separate, deliberate act.
 */
class BangladeshSourceCatalogue
{
    /**
     * @return list<array{
     *     slug: string,
     *     name: string,
     *     class: string,
     *     homepage: string,
     *     authority: string,
     *     authorisation: string,
     *     endpoints: list<array<string, mixed>>
     * }>
     */
    public function definitions(): array
    {
        return [
            [
                'slug' => 'bppa-egp',
                'name' => 'Bangladesh Public Procurement Authority (e-GP)',
                'class' => 'government',
                'homepage' => 'https://www.eprocure.gov.bd/',
                'authority' => 'Bangladesh Public Procurement Authority',
                'authorisation' => 'I authorise CivicLens to fetch public tender notices from eprocure.gov.bd at a rate-limited pace.',
                'endpoints' => [[
                    'name' => 'Public tender and proposal listing',
                    // The listing servlet answers POST and nothing else, so this
                    // is the one endpoint that needs the posting connector.
                    'connector_type' => 'egp_tender_listing',
                    'base_url' => 'https://www.eprocure.gov.bd/TenderDetailsServlet',
                    'allowed_hosts' => ['www.eprocure.gov.bd'],
                    'allowed_path_prefixes' => ['/TenderDetailsServlet'],
                    'access_decision' => 'operator-authorised-public-notices',
                    'rate_limit_per_minute' => 6,
                ]],
            ],
            [
                'slug' => 'cag-bangladesh',
                'name' => 'Comptroller and Auditor General of Bangladesh',
                'class' => 'government',
                'homepage' => 'https://cag.org.bd/',
                'authority' => 'Office of the Comptroller and Auditor General of Bangladesh',
                'authorisation' => 'I authorise CivicLens to fetch public audit reports from cag.org.bd rate-limited.',
                'endpoints' => [
                    [
                        // The audit category pages return placeholder text, so the
                        // storage path is the route that actually carries documents.
                        'name' => 'Published audit document storage',
                        'connector_type' => 'direct_download',
                        'base_url' => 'https://cag.org.bd/storage/app/uploads/public/',
                        'allowed_hosts' => ['cag.org.bd'],
                        'allowed_path_prefixes' => ['/storage/app/uploads/public', '/storage/app/media'],
                        'access_decision' => 'operator-authorised-public-audit',
                        'rate_limit_per_minute' => 4,
                    ],
                ],
            ],
            [
                'slug' => 'dgcivil-bangladesh',
                'name' => 'Directorate General of Civil Audit, Bangladesh',
                'class' => 'government',
                'homepage' => 'https://dgcivil-cagbd.org/',
                'authority' => 'Directorate General of Civil Audit',
                // A separate host under a separate directorate, so it carries its
                // own recorded authorisation rather than riding on the CAG one.
                'authorisation' => 'I authorise CivicLens to fetch public audit reports from dgcivil-cagbd.org rate-limited.',
                'endpoints' => [
                    [
                        'name' => 'Civil Audit Directorate report archive',
                        'connector_type' => 'static_html',
                        'base_url' => 'https://dgcivil-cagbd.org/audit-report/',
                        'allowed_hosts' => ['dgcivil-cagbd.org'],
                        'allowed_path_prefixes' => ['/audit-report', '/wp-content/uploads'],
                        'access_decision' => 'operator-authorised-public-audit',
                        'rate_limit_per_minute' => 4,
                    ],
                ],
            ],
        ];
    }
}
