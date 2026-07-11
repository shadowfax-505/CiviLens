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
    ],

    'documents' => [
        'disk' => env('DOCUMENT_STORAGE_DISK', env('FILESYSTEM_DISK', 'local')),
        'max_upload_kb' => (int) env('DOCUMENT_MAX_UPLOAD_KB', 20480),
        'allowed_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'txt'],
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
