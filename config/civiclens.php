<?php

use App\Models\AdministrativeUnion;
use App\Models\Agency;
use App\Models\Budget;
use App\Models\Contract;
use App\Models\Country;
use App\Models\District;
use App\Models\Division;
use App\Models\Document;
use App\Models\IntelligenceIndicator;
use App\Models\Organization;
use App\Models\ProcurementPlan;
use App\Models\Project;
use App\Models\Tender;
use App\Models\Upazila;
use App\Models\Ward;

return [
    'roles' => [
        'admin' => 'admin',
        'staff' => 'staff',
        'citizen' => 'citizen',
    ],

    'permissions' => [
        'users_manage' => 'users.manage',
        'roles_manage' => 'roles.manage',
        'locations_manage' => 'locations.manage',
        'agencies_manage' => 'agencies.manage',
        'projects_manage' => 'projects.manage',
        'budgets_manage' => 'budgets.manage',
        'procurements_manage' => 'procurements.manage',
        'contractors_manage' => 'contractors.manage',
        'documents_manage' => 'documents.manage',
        'search_manage' => 'search.manage',
        'change_requests_submit' => 'change_requests.submit',
        'analytics_view' => 'analytics.view',
        'analytics_manage' => 'analytics.manage',
        'intelligence_manage' => 'intelligence.manage',
        'reports_submit' => 'reports.submit',
        'citizen_reports_manage' => 'citizen_reports.manage',
        'sources_manage' => 'sources.manage',
    ],

    'extraction' => [
        'pdftotext_binary' => env('EXTRACTION_PDFTOTEXT_BINARY', 'pdftotext'),
        'pdfinfo_binary' => env('EXTRACTION_PDFINFO_BINARY', 'pdfinfo'),
        'process_timeout_seconds' => (int) env('EXTRACTION_PROCESS_TIMEOUT', 60),
        'max_pages' => (int) env('EXTRACTION_MAX_PAGES', 500),
        'max_bytes' => (int) env('EXTRACTION_MAX_BYTES', 52428800),
        // Characters per square inch below which a page is treated as lacking a usable
        // text layer and routed to OCR. Reported alongside results so the operating
        // point is reproducible rather than implicit.
        'native_density_threshold' => (float) env('EXTRACTION_NATIVE_DENSITY_THRESHOLD', 1.5),
        'default_page_width_points' => 595.276,
        'default_page_height_points' => 841.89,
        'ocr' => [
            'tesseract_binary' => env('EXTRACTION_TESSERACT_BINARY', 'tesseract'),
            'pdftoppm_binary' => env('EXTRACTION_PDFTOPPM_BINARY', 'pdftoppm'),
            'languages' => env('EXTRACTION_OCR_LANGUAGES', 'ben+eng'),
            'primary_dpi' => (int) env('EXTRACTION_OCR_PRIMARY_DPI', 150),
            'enhanced_dpi' => (int) env('EXTRACTION_OCR_ENHANCED_DPI', 300),
            'primary_psm' => (int) env('EXTRACTION_OCR_PRIMARY_PSM', 3),
            'enhanced_psm' => (int) env('EXTRACTION_OCR_ENHANCED_PSM', 6),
            // Mean per-word confidence at or above which a pass is accepted. Below it,
            // exactly one enhanced pass is permitted; still below, the page abstains to
            // manual review rather than publishing text nobody vouched for.
            'accept_confidence' => (float) env('EXTRACTION_OCR_ACCEPT_CONFIDENCE', 80.0),
            'timeout_seconds' => (int) env('EXTRACTION_OCR_TIMEOUT', 120),
        ],

        // Column-gap allowance for the jump from a label to its value, in label
        // heights. Tabular publisher layouts need a wider allowance than dense
        // prose. Measured on real e-GP tender notices, the gutter runs to about
        // six label heights, and the improvement loop found 8 on CPTU documents.
        'kv_gap_multiple' => (float) env('EXTRACTION_KV_GAP_MULTIPLE', 8),

        // Allowance between consecutive words *within* a value, in label
        // heights. Deliberately much tighter: on the same notices the next
        // field's label sits closer to the end of a value than the value sat to
        // its own label, so reading on at column width swallows it.
        'kv_value_gap_multiple' => (float) env('EXTRACTION_KV_VALUE_GAP_MULTIPLE', 1.5),

        // Bounds for spreadsheet reading. A spreadsheet is a compact way to
        // describe an enormous amount of data: the risk is less a hostile
        // publisher than an ordinary file declaring a million empty rows, so
        // the limits are enforced while reading rather than after loading.
        'spreadsheet' => [
            'max_rows' => (int) env('EXTRACTION_SPREADSHEET_MAX_ROWS', 5000),
            'max_columns' => (int) env('EXTRACTION_SPREADSHEET_MAX_COLUMNS', 64),
            'max_archive_entries' => (int) env('EXTRACTION_SPREADSHEET_MAX_ENTRIES', 512),
            'max_uncompressed_bytes' => (int) env('EXTRACTION_SPREADSHEET_MAX_UNCOMPRESSED_BYTES', 268435456),
            'max_compression_ratio' => (float) env('EXTRACTION_SPREADSHEET_MAX_RATIO', 200),
        ],

        'native_media_types' => [
            'application/pdf' => 'pdf',
            'text/plain' => 'text',
            'text/html' => 'text',
            'text/csv' => 'spreadsheet',
            'application/vnd.ms-excel' => 'spreadsheet',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'spreadsheet',
            'application/vnd.oasis.opendocument.spreadsheet' => 'spreadsheet',
            'text/xml' => 'text',
            'application/xml' => 'text',
            'application/json' => 'text',
        ],
    ],

    'intelligence' => [
        // Fewest comparable procurements required before a peer comparison is
        // stated at all. Below this a difference is sampling noise.
        'minimum_peer_cohort' => (int) env('CIVICLENS_MINIMUM_PEER_COHORT', 8),
    ],

    'documents' => [
        'disk' => env('DOCUMENT_STORAGE_DISK', env('FILESYSTEM_DISK', 'local')),
        'max_upload_kb' => (int) env('DOCUMENT_MAX_UPLOAD_KB', 20480),
        'allowed_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'txt'],
    ],

    'maps' => [
        'public_marker_limit' => (int) env('MAP_PUBLIC_MARKER_LIMIT', 250),
        'admin_marker_limit' => (int) env('MAP_ADMIN_MARKER_LIMIT', 500),
        'default_latitude' => (float) env('MAP_DEFAULT_LATITUDE', 23.685),
        'default_longitude' => (float) env('MAP_DEFAULT_LONGITUDE', 90.3563),
        'max_viewport_latitude_span' => (float) env('MAP_MAX_VIEWPORT_LATITUDE_SPAN', 30),
        'max_viewport_longitude_span' => (float) env('MAP_MAX_VIEWPORT_LONGITUDE_SPAN', 30),
        'tile_url' => env('MAP_TILE_URL', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'tile_attribution' => env('MAP_TILE_ATTRIBUTION', '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'),
    ],

    'earth_journey' => [
        'enabled' => (bool) env('CIVICLENS_EARTH_JOURNEY_ENABLED', false),
    ],

    'notifications' => [
        'categories' => [
            'major_changes' => ['label' => 'All approved major changes', 'default' => true],
            'procurement_updates' => ['label' => 'Procurement updates', 'default' => false],
            'budget_updates' => ['label' => 'Budget updates', 'default' => false],
            'audit_updates' => ['label' => 'Audit updates', 'default' => false],
            'agency_publications' => ['label' => 'Agency publications', 'default' => false],
        ],
    ],

    'ingestion' => [
        'enabled' => filter_var(env('INGESTION_ENABLED', false), FILTER_VALIDATE_BOOL),
        'artifact_disk' => env('INGESTION_ARTIFACT_DISK', env('DOCUMENT_STORAGE_DISK', 'local')),
        'user_agent' => env('INGESTION_USER_AGENT', 'CivicLensBot/2.0 (+'.rtrim((string) env('APP_URL', 'http://localhost'), '/').'/about)'),
        'max_redirects' => (int) env('INGESTION_MAX_REDIRECTS', 3),
        'discovery_max_content_bytes' => (int) env('INGESTION_DISCOVERY_MAX_BYTES', 5242880),
        'absolute_max_content_bytes' => (int) env('INGESTION_ABSOLUTE_MAX_BYTES', 52428800),
        'max_discovered_per_run' => (int) env('INGESTION_MAX_DISCOVERED_PER_RUN', 250),
        'pin_resolved_address' => filter_var(env('INGESTION_PIN_RESOLVED_ADDRESS', true), FILTER_VALIDATE_BOOL),
        'allow_unpinned_egress' => filter_var(env('INGESTION_ALLOW_UNPINNED_EGRESS', false), FILTER_VALIDATE_BOOL),
        'malware_scanner_binary' => env('INGESTION_MALWARE_SCANNER_BINARY', 'clamdscan'),
        'malware_scan_timeout_seconds' => (int) env('INGESTION_MALWARE_SCAN_TIMEOUT', 30),
        'allowed_media_types' => [
            'application/octet-stream',
            'application/json',
            'application/pdf',
            'application/rtf',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword',
            'application/xml',
            'image/jpeg',
            'image/png',
            'image/tiff',
            'text/csv',
            'text/html',
            'text/plain',
            'text/xml',
        ],
    ],

    'search' => [
        'provider' => env('SEARCH_PROVIDER', 'database'),
        'cache_ttl_minutes' => (int) env('SEARCH_CACHE_TTL_MINUTES', 10),
        'registry' => [
            'projects' => Project::class,
            'budgets' => Budget::class,
            'procurement_plans' => ProcurementPlan::class,
            'tenders' => Tender::class,
            'contracts' => Contract::class,
            'organizations' => Organization::class,
            'documents' => Document::class,
            'agencies' => Agency::class,
            'countries' => Country::class,
            'divisions' => Division::class,
            'districts' => District::class,
            'upazilas' => Upazila::class,
            'unions' => AdministrativeUnion::class,
            'wards' => Ward::class,
            'intelligence' => IntelligenceIndicator::class,
        ],
        'future_providers' => [
            'scout' => 'Documented placeholder for Laravel Scout-backed search.',
            'meilisearch' => 'Documented placeholder for direct Meilisearch-backed search.',
            'opensearch' => 'Documented placeholder for OpenSearch-backed search.',
        ],
    ],
];
