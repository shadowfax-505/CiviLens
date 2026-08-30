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
                'slug' => 'imed-bangladesh',
                'name' => 'Implementation Monitoring and Evaluation Division',
                'class' => 'government',
                'homepage' => 'https://imed.gov.bd/',
                'authority' => 'Implementation Monitoring and Evaluation Division, Ministry of Planning',
                'authorisation' => 'I authorise CivicLens to fetch public evaluation and annual reports from imed.gov.bd rate-limited.',
                'endpoints' => [
                    [
                        // Rendered, because the list is built by JavaScript: the
                        // HTML this page serves carries forty-four links and no
                        // documents. The files themselves are not on the
                        // ministry's host at all — they sit in the government's
                        // object storage, which is why it is allowlisted here.
                        'name' => 'Annual and evaluation reports',
                        'connector_type' => 'browser',
                        'base_url' => 'https://imed.gov.bd/pages/annual-reports/',
                        'allowed_hosts' => ['imed.gov.bd', 'objectstorage.ap-dcc-gazipur-1.oraclecloud15.com'],
                        'allowed_path_prefixes' => ['/pages', '/site', '/n'],
                        'access_decision' => 'operator-authorised-public-material',
                        'rate_limit_per_minute' => 4,
                    ],
                ],
            ],
            [
                'slug' => 'bgpress-gazette',
                'name' => 'Bangladesh Government Press gazette',
                'class' => 'government',
                'homepage' => 'https://www.dpp.gov.bd/bgpress',
                'authority' => 'Department of Printing and Publications',
                'authorisation' => 'I authorise CivicLens to fetch published gazettes from dpp.gov.bd rate-limited.',
                'endpoints' => [
                    [
                        // The gazette index, which is a page the site publishes
                        // rather than a range of numbers to walk. Its documents
                        // carry no file extension and are identified by what the
                        // server returns, not by their URL.
                        'name' => 'Extraordinary gazette index',
                        'connector_type' => 'browser',
                        'base_url' => 'https://www.dpp.gov.bd/bgpress/index.php/document/gazettes/140',
                        'allowed_hosts' => ['www.dpp.gov.bd'],
                        'allowed_path_prefixes' => ['/bgpress'],
                        'access_decision' => 'operator-authorised-public-material',
                        // One a minute. At four this host stopped answering
                        // altogether after roughly a hundred requests — port 443
                        // now refuses the connection while port 80 returns 403 —
                        // and a rate that gets a crawler blocked is not a rate,
                        // it is a way of losing a source.
                        'rate_limit_per_minute' => 1,
                        'crawl_interval_minutes' => 1440,
                    ],
                ],
            ],
            [
                'slug' => 'parliament-bangladesh',
                'name' => 'Bangladesh Parliament',
                'class' => 'government',
                'homepage' => 'https://www.parliament.gov.bd/',
                'authority' => 'Bangladesh Parliament Secretariat',
                'authorisation' => 'I authorise CivicLens to fetch public parliamentary publications from parliament.gov.bd rate-limited.',
                'endpoints' => [
                    [
                        'name' => 'Parliamentary publications',
                        'connector_type' => 'browser',
                        'base_url' => 'https://www.parliament.gov.bd/',
                        'allowed_hosts' => ['www.parliament.gov.bd'],
                        'allowed_path_prefixes' => ['/api/upload', '/en', '/bn', '/'],
                        'access_decision' => 'operator-authorised-public-material',
                        'rate_limit_per_minute' => 4,
                    ],
                ],
            ],
            [
                'slug' => 'supremecourt-bangladesh',
                'name' => 'Supreme Court of Bangladesh',
                'class' => 'government',
                'homepage' => 'https://www.supremecourt.gov.bd/',
                'authority' => 'Supreme Court of Bangladesh',
                'authorisation' => 'I authorise CivicLens to fetch published High Court Division judgments, orders and the Supreme Court Online Bulletin from supremecourt.gov.bd rate-limited.',
                'endpoints' => [
                    [
                        // Judgments and orders of the High Court Division.
                        // div_id=2 is the High Court Division; div_id=1 is the
                        // Appellate Division, whose judgments bind it.
                        //
                        // The page carries a POST form and an AJAX search, and
                        // reading only that would put this source in the same
                        // box as e-GP's detail servlet — reachable by POST
                        // alone. It is not: the listing's own pagination
                        // handler builds `&start=` as a plain URL, and a GET at
                        // start=0 returns fifty judgment PDFs. Verified before
                        // registering, which is the only reason this endpoint
                        // is not recorded as unreachable.
                        'name' => 'High Court Division judgments and orders',
                        'connector_type' => 'paginated_html_listing',
                        'base_url' => 'https://www.supremecourt.gov.bd/web/?page=judgments.php&menu=00&div_id=2',
                        'allowed_hosts' => ['www.supremecourt.gov.bd', 'supremecourt.gov.bd'],
                        'allowed_path_prefixes' => ['/web', '/resources'],
                        'access_decision' => 'operator-authorised-public-judgments',
                        // One request every ten seconds, from evidence rather
                        // than caution alone. Registered at one a minute on the
                        // bgpress lesson — that host stopped answering at four
                        // and now refuses 443 outright — and then the discovery
                        // walk accidentally put fifty requests to this host
                        // inside a minute, which it served without complaint and
                        // still answers. Six a minute is well inside what it
                        // tolerates and puts 2,600 judgments inside a working
                        // day rather than two.
                        'rate_limit_per_minute' => 6,
                        'connector_options' => [
                            'offset_parameter' => 'start',
                            'page_size' => 50,
                            // Fifty pages a run at a request a minute is under
                            // an hour of the court's time, and the cursor
                            // carries the offset so the next run continues.
                            'pages_per_run' => 50,
                        ],
                    ],
                    [
                        // The Supreme Court Online Bulletin: reported decisions
                        // with a citation, keywords and a short holding. The
                        // most structured court material of the four, and the
                        // reason it matters here is that the holding is stated
                        // by the court rather than inferred by us.
                        'name' => 'Supreme Court Online Bulletin',
                        'connector_type' => 'static_html',
                        'base_url' => 'https://www.supremecourt.gov.bd/web/?page=bulletin.php&menu=10',
                        'allowed_hosts' => ['www.supremecourt.gov.bd', 'supremecourt.gov.bd'],
                        'allowed_path_prefixes' => ['/web', '/resources'],
                        'access_decision' => 'operator-authorised-public-judgments',
                        'rate_limit_per_minute' => 1,
                    ],
                ],
            ],
            [
                'slug' => 'minlaw-bangladesh',
                'name' => 'Ministry of Law, Justice and Parliamentary Affairs (Bangladesh Laws)',
                'class' => 'government',
                'homepage' => 'http://bdlaws.minlaw.gov.bd/',
                'authority' => 'Legislative and Parliamentary Affairs Division, Ministry of Law, Justice and Parliamentary Affairs',
                'authorisation' => 'I authorise CivicLens to fetch the published text of the Constitution, Acts and Ordinances from bdlaws.minlaw.gov.bd rate-limited.',
                'endpoints' => [
                    [
                        // Statute text, so a citation in a judgment resolves to
                        // the provision it names rather than staying a string.
                        //
                        // Plaintext by recorded exception. This host answers 200
                        // over HTTP and refuses HTTPS entirely — not a
                        // misconfigured chain that build-ca-bundle repairs, no
                        // TLS listener at all. Without the exception the
                        // statutes cited by every judgment in the corpus are
                        // unreachable.
                        'name' => 'Constitution, Acts and Ordinances',
                        'connector_type' => 'static_html',
                        'base_url' => 'http://bdlaws.minlaw.gov.bd/laws-of-bangladesh.html',
                        'allowed_hosts' => ['bdlaws.minlaw.gov.bd'],
                        'allowed_path_prefixes' => ['/'],
                        'access_decision' => 'operator-authorised-public-legislation',
                        'plaintext_override_reason' => 'Operator decision: bdlaws.minlaw.gov.bd serves the Constitution and every Act over HTTP and offers no HTTPS listener. Fetched over plaintext so statutory citations resolve. The provenance cost is real and stated rather than hidden — bytes fetched over HTTP can be altered in transit, so a checksum of them attests to what we received and not to what the ministry served. Statute text is corroborable against the printed gazette; nothing certified rests on these bytes alone.',
                        'rate_limit_per_minute' => 6,
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
                'slug' => 'iati',
                'name' => 'IATI Datastore',
                'class' => 'multilateral',
                'homepage' => 'https://iatistandard.org/',
                'authority' => 'International Aid Transparency Initiative',
                'authorisation' => 'I authorise CivicLens to fetch IATI Datastore activity records for Bangladesh under a Full Access subscription key.',
                'endpoints' => [
                    [
                        // 10,913 activities recorded against Bangladesh, each a
                        // record rather than a document. The value here is the
                        // reporting organisation and the participating org
                        // names: donor-side spending that the Bangladeshi
                        // publishers do not report at all, which is the only
                        // route to a figure appearing in two independent places.
                        'name' => 'Bangladesh aid activity records',
                        'connector_type' => 'paginated_api',
                        'base_url' => 'https://api.iatistandard.org/datastore/activity/select?q=recipient_country_code%3ABD&wt=json',
                        'connector_options' => [
                            'pagination' => 'offset',
                            'page_parameter' => 'start',
                            'size_parameter' => 'rows',
                            'page_size' => 50,
                            'pages_per_run' => 4,
                            'records_key' => 'response.docs',
                            // The header name and the *name* of the config key,
                            // never the key. Endpoint rows are backed up, shown
                            // in the admin registry and readable by anyone with
                            // database access.
                            'auth' => [
                                'header' => 'Ocp-Apim-Subscription-Key',
                                'config' => 'civiclens.ingestion.keys.iati',
                            ],
                        ],
                        'allowed_hosts' => ['api.iatistandard.org'],
                        'allowed_path_prefixes' => ['/datastore'],
                        'access_decision' => 'operator-authorised-subscription-api',
                        'rate_limit_per_minute' => 6,
                    ],
                ],
            ],
            [
                'slug' => 'opensanctions',
                'name' => 'OpenSanctions',
                'class' => 'civil-society',
                'homepage' => 'https://www.opensanctions.org/',
                'authority' => 'OpenSanctions / OpenSanctions Datenbanken GmbH',
                // Bulk downloads carry no key at all — the API key sells the
                // hosted matching service, not the data. Published under
                // CC BY-NC 4.0, which permits this research and forbids
                // commercial reuse, so anything derived from it must say so.
                'authorisation' => 'Public bulk data published under CC BY-NC 4.0; used for non-commercial academic research with attribution.',
                'endpoints' => [
                    [
                        // data.opensanctions.org answers "User-agent: * /
                        // Disallow: /" for the whole bulk host. The operator has
                        // recorded a decision to fetch anyway, on legal advice,
                        // for a non-commercial public-benefit project, and that
                        // decision is stored on this endpoint alone and logged
                        // on every use rather than applied as a global setting.
                        //
                        // The data is CC BY-NC 4.0, so attribution and the
                        // non-commercial restriction travel with anything
                        // derived from it. Rate limited to two a minute for a
                        // file fetched once a day, because a recorded right to
                        // fetch is not a reason to be expensive to serve.
                        //
                        // 4,283 entities in 477KB. The consolidated debarment
                        // and sanctions files are 416MB and 352MB, past the byte
                        // ceiling this pipeline fetches under.
                        'name' => 'World Bank debarred providers',
                        'connector_type' => 'direct_download',
                        'base_url' => 'https://data.opensanctions.org/datasets/latest/worldbank_debarred/targets.simple.csv',
                        'allowed_hosts' => ['data.opensanctions.org'],
                        'allowed_path_prefixes' => ['/datasets', '/artifacts'],
                        'access_decision' => 'operator-authorised-public-data',
                        'robots_override_reason' => 'Operator decision on legal advice: non-commercial public-benefit research, data published under CC BY-NC 4.0, one small file fetched daily. robots.txt disallows the bulk host to all agents.',
                        'rate_limit_per_minute' => 2,
                        'crawl_interval_minutes' => 1440,
                    ],
                ],
            ],
            [
                'slug' => 'wikidata',
                'name' => 'Wikidata',
                'class' => 'civil-society',
                'homepage' => 'https://www.wikidata.org/',
                'authority' => 'Wikimedia Foundation',
                // CC0: no rights reserved, so nothing licence-wise travels with
                // what is derived from it.
                'authorisation' => 'Public query service; data published under CC0 1.0.',
                'endpoints' => [
                    [
                        // 610 Bangladeshi companies with labels, industries and
                        // any LEI recorded. Thin, and skewed to utilities,
                        // banks and conglomerates rather than the construction
                        // firms that fill audit reports — only two rows carry an
                        // LEI. Registered as a reference table to match large
                        // contractors against, not as a registry substitute.
                        //
                        // query.wikidata.org disallows /sparql to every agent.
                        // The operator has recorded a decision to query it
                        // anyway: one request a day for a result set of a few
                        // hundred rows, which is far below the load that
                        // directive exists to prevent.
                        'name' => 'Bangladeshi company entities',
                        'connector_type' => 'direct_download',
                        'base_url' => 'https://query.wikidata.org/sparql?format=json&query=SELECT%20%3Fcompany%20%3FcompanyLabel%20%3Flei%20%3Finception%20%3FindustryLabel%20WHERE%20%7B%20%3Fcompany%20wdt%3AP17%20wd%3AQ902%20%3B%20wdt%3AP31%2Fwdt%3AP279%2A%20wd%3AQ4830453%20.%20OPTIONAL%20%7B%20%3Fcompany%20wdt%3AP1278%20%3Flei%20%7D%20OPTIONAL%20%7B%20%3Fcompany%20wdt%3AP571%20%3Finception%20%7D%20OPTIONAL%20%7B%20%3Fcompany%20wdt%3AP452%20%3Findustry%20%7D%20SERVICE%20wikibase%3Alabel%20%7B%20bd%3AserviceParam%20wikibase%3Alanguage%20%22en%2Cbn%22.%20%7D%20%7D',
                        'allowed_hosts' => ['query.wikidata.org'],
                        'allowed_path_prefixes' => ['/sparql'],
                        'access_decision' => 'operator-authorised-public-data',
                        'robots_override_reason' => 'Operator decision on legal advice: non-commercial public-benefit research. One query a day returning a few hundred rows, well under the load robots.txt exists to prevent. Data is CC0.',
                        'rate_limit_per_minute' => 1,
                        'crawl_interval_minutes' => 1440,
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
