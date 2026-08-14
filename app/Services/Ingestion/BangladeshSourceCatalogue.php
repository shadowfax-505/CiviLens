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
                        // Carries real documents, but cannot be discovered from.
                        // The category pages return placeholder text, and the
                        // storage path has no index: a trial run against it
                        // returned exactly one resource, the directory URL
                        // itself. Enumerating it would mean guessing filenames,
                        // which is neither discovery nor something the recorded
                        // authorisation covers.
                        //
                        // Left registered and paused rather than deleted: the
                        // authorisation is real and the route is right, so what
                        // is missing is an index to read. The Civil Audit
                        // Directorate archive is the working audit source.
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
                'slug' => 'worldbank-bangladesh',
                'name' => 'World Bank project and lending records for Bangladesh',
                'class' => 'multilateral',
                'homepage' => 'https://projects.worldbank.org/',
                'authority' => 'The World Bank Group',
                'authorisation' => 'I authorise CivicLens to fetch the World Bank public projects API for Bangladesh rate-limited.',
                'endpoints' => [
                    [
                        // Records rather than documents: 407 Bangladesh projects
                        // with no file behind a row. Each page is archived as
                        // the publication it is, hashed and versioned like any
                        // other artifact.
                        'name' => 'Bangladesh project records',
                        'connector_type' => 'paginated_api',
                        'base_url' => 'https://search.worldbank.org/api/v2/projects?format=json&countrycode=BD',
                        'connector_options' => [
                            'pagination' => 'offset',
                            'page_parameter' => 'os',
                            'size_parameter' => 'rows',
                            'page_size' => 50,
                            'pages_per_run' => 4,
                            'records_key' => 'projects',
                        ],
                        'allowed_hosts' => ['search.worldbank.org'],
                        'allowed_path_prefixes' => ['/api'],
                        'access_decision' => 'operator-authorised-public-data',
                        'rate_limit_per_minute' => 6,
                    ],
                ],
            ],
            [
                'slug' => 'gleif',
                'name' => 'Global Legal Entity Identifier Foundation',
                'class' => 'multilateral',
                'homepage' => 'https://www.gleif.org/',
                'authority' => 'GLEIF',
                'authorisation' => 'I authorise CivicLens to fetch the public GLEIF LEI records API rate-limited.',
                'endpoints' => [
                    [
                        // Entity records for Bangladesh-registered organisations,
                        // which is what a contractor name has to be matched
                        // against before anything is said about who it is.
                        'name' => 'Bangladesh legal entity records',
                        'connector_type' => 'paginated_api',
                        'base_url' => 'https://api.gleif.org/api/v1/lei-records?filter%5Bentity.legalAddress.country%5D=BD',
                        'connector_options' => [
                            'pagination' => 'page',
                            'page_parameter' => 'page[number]',
                            'size_parameter' => 'page[size]',
                            'page_size' => 50,
                            'pages_per_run' => 4,
                            'records_key' => 'data',
                        ],
                        'allowed_hosts' => ['api.gleif.org'],
                        'allowed_path_prefixes' => ['/api'],
                        'access_decision' => 'operator-authorised-public-data',
                        'rate_limit_per_minute' => 6,
                    ],
                ],
            ],
            [
                'slug' => 'cbad-bangladesh',
                'name' => 'Directorate of Constitutional Bodies Audit, Bangladesh',
                'class' => 'government',
                'homepage' => 'https://cbad.org.bd/',
                'authority' => 'Directorate of Constitutional Bodies Audit',
                'authorisation' => 'I authorise CivicLens to fetch public audit reports from cbad.org.bd rate-limited.',
                'endpoints' => [
                    [
                        // The listing CAG itself does not publish. Its own site
                        // has no index for /storage, and two of the three
                        // documents on this page are hosted there, which is why
                        // cag.org.bd is allowlisted from here and narrowed to
                        // the storage prefix.
                        'name' => 'Compliance audit report listing',
                        'connector_type' => 'static_html',
                        'base_url' => 'https://cbad.org.bd/page/compliance-audit-reports',
                        'allowed_hosts' => ['cbad.org.bd', 'cag.org.bd'],
                        'allowed_path_prefixes' => ['/page', '/public/files', '/storage/app'],
                        'access_decision' => 'operator-authorised-public-audit',
                        'rate_limit_per_minute' => 4,
                    ],
                    [
                        'name' => 'Performance audit report listing',
                        'connector_type' => 'static_html',
                        'base_url' => 'https://cbad.org.bd/page/performance-audit-reports',
                        'allowed_hosts' => ['cbad.org.bd', 'cag.org.bd'],
                        'allowed_path_prefixes' => ['/page', '/public/files', '/storage/app'],
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
