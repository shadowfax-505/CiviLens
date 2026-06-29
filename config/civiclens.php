<?php

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
        'analytics_view' => 'analytics.view',
        'reports_submit' => 'reports.submit',
    ],

    'documents' => [
        'disk' => env('DOCUMENT_STORAGE_DISK', env('FILESYSTEM_DISK', 'local')),
        'max_upload_kb' => (int) env('DOCUMENT_MAX_UPLOAD_KB', 20480),
        'allowed_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'txt'],
    ],
];
