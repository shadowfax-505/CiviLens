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
        'analytics_view' => 'analytics.view',
        'reports_submit' => 'reports.submit',
    ],
];
