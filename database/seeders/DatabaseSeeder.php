<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\AgencyType;
use App\Models\Budget;
use App\Models\BudgetCategory;
use App\Models\BudgetStatus;
use App\Models\BudgetTransactionType;
use App\Models\BudgetType;
use App\Models\Country;
use App\Models\FiscalYear;
use App\Models\FundingSource;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectPriority;
use App\Models\ProjectStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $accessGroup = PermissionGroup::query()->firstOrCreate(
            ['slug' => 'access-management'],
            ['name' => 'Access Management', 'description' => 'Identity, users, roles, and permissions.'],
        );
        $projectGroup = PermissionGroup::query()->firstOrCreate(
            ['slug' => 'civic-data'],
            ['name' => 'Civic Data', 'description' => 'Projects, analytics, and civic reports.'],
        );
        $registryGroup = PermissionGroup::query()->firstOrCreate(
            ['slug' => 'registry-management'],
            ['name' => 'Registry Management', 'description' => 'Geographic references and government agency registry.'],
        );

        $permissions = collect([
            ['permission_group_id' => $accessGroup->id, 'name' => 'Manage Users', 'slug' => config('civiclens.permissions.users_manage'), 'description' => 'Manage platform users and access.'],
            ['permission_group_id' => $accessGroup->id, 'name' => 'Manage Roles', 'slug' => config('civiclens.permissions.roles_manage'), 'description' => 'Manage roles and permission assignments.'],
            ['permission_group_id' => $registryGroup->id, 'name' => 'Manage Locations', 'slug' => config('civiclens.permissions.locations_manage'), 'description' => 'Manage normalized geographic reference data.'],
            ['permission_group_id' => $registryGroup->id, 'name' => 'Manage Agencies', 'slug' => config('civiclens.permissions.agencies_manage'), 'description' => 'Manage government agency records and assignments.'],
            ['permission_group_id' => $projectGroup->id, 'name' => 'Manage Projects', 'slug' => config('civiclens.permissions.projects_manage'), 'description' => 'Create and update civic project records.'],
            ['permission_group_id' => $projectGroup->id, 'name' => 'Manage Budgets', 'slug' => config('civiclens.permissions.budgets_manage'), 'description' => 'Manage project budgets, revisions, and financial transactions.'],
            ['permission_group_id' => $projectGroup->id, 'name' => 'View Analytics', 'slug' => config('civiclens.permissions.analytics_view'), 'description' => 'View platform analytics dashboards.'],
            ['permission_group_id' => $projectGroup->id, 'name' => 'Submit Reports', 'slug' => config('civiclens.permissions.reports_submit'), 'description' => 'Submit civic reports and field updates.'],
        ])->map(fn (array $permission) => Permission::query()->firstOrCreate(
            ['slug' => $permission['slug']],
            $permission,
        ));

        $admin = Role::query()->firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Administrator', 'description' => 'Platform administrator with full operational access.'],
        );
        $staff = Role::query()->firstOrCreate(
            ['slug' => 'staff'],
            ['name' => 'Government Staff', 'description' => 'Staff user who maintains civic data.'],
        );
        $citizen = Role::query()->firstOrCreate(
            ['slug' => 'citizen'],
            ['name' => 'Citizen', 'description' => 'Public user who can browse and submit civic reports.'],
        );

        $admin->permissions()->sync($permissions->pluck('id'));
        $staff->permissions()->sync($permissions->whereIn('slug', [
            config('civiclens.permissions.agencies_manage'),
            config('civiclens.permissions.locations_manage'),
            config('civiclens.permissions.projects_manage'),
            config('civiclens.permissions.budgets_manage'),
            config('civiclens.permissions.analytics_view'),
        ])->pluck('id'));
        $citizen->permissions()->sync($permissions->where('slug', config('civiclens.permissions.reports_submit'))->pluck('id'));

        $baselineAdmin = User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
                'email_verified_at' => now(),
                'is_active' => true,
                'locked_at' => null,
            ],
        );

        $baselineAdmin->roles()->sync([$admin->id]);

        $bangladesh = Country::query()->firstOrCreate(
            ['iso2' => 'BD'],
            [
                'name' => 'Bangladesh',
                'iso3' => 'BGD',
                'phone_code' => '+880',
                'latitude' => 23.685,
                'longitude' => 90.3563,
            ],
        );

        $ministry = AgencyType::query()->firstOrCreate(
            ['slug' => 'ministry'],
            ['name' => 'Ministry', 'description' => 'National government ministry.'],
        );
        AgencyType::query()->firstOrCreate(
            ['slug' => 'department'],
            ['name' => 'Department', 'description' => 'Department or directorate under a parent agency.'],
        );
        AgencyType::query()->firstOrCreate(
            ['slug' => 'regional-office'],
            ['name' => 'Regional Office', 'description' => 'Regional office for subnational operations.'],
        );
        AgencyType::query()->firstOrCreate(
            ['slug' => 'local-office'],
            ['name' => 'Local Office', 'description' => 'Local office serving a defined geography.'],
        );

        $baselineAgency = Agency::query()->firstOrCreate(
            ['slug' => 'ministry-of-planning'],
            [
                'agency_type_id' => $ministry->id,
                'country_id' => $bangladesh->id,
                'name' => 'Ministry of Planning',
                'short_name' => 'MoP',
                'status' => 'active',
                'description' => 'Baseline agency for CivicLens reference data.',
            ],
        );

        $transport = ProjectCategory::query()->firstOrCreate(
            ['slug' => 'transport'],
            ['name' => 'Transport', 'description' => 'Road, bridge, rail, and public transport projects.'],
        );
        ProjectCategory::query()->firstOrCreate(
            ['slug' => 'water-sanitation'],
            ['name' => 'Water & Sanitation', 'description' => 'Water supply, drainage, and sanitation projects.'],
        );
        ProjectCategory::query()->firstOrCreate(
            ['slug' => 'public-buildings'],
            ['name' => 'Public Buildings', 'description' => 'Government buildings, schools, hospitals, and civic facilities.'],
        );

        $planning = ProjectStatus::query()->firstOrCreate(
            ['slug' => 'planning'],
            ['name' => 'Planning', 'description' => 'Project is in planning or feasibility review.', 'sort_order' => 10],
        );
        ProjectStatus::query()->firstOrCreate(
            ['slug' => 'in-progress'],
            ['name' => 'In Progress', 'description' => 'Project implementation is underway.', 'sort_order' => 20],
        );
        ProjectStatus::query()->firstOrCreate(
            ['slug' => 'completed'],
            ['name' => 'Completed', 'description' => 'Project work has been completed.', 'sort_order' => 30, 'is_terminal' => true],
        );
        ProjectStatus::query()->firstOrCreate(
            ['slug' => 'suspended'],
            ['name' => 'Suspended', 'description' => 'Project is temporarily suspended.', 'sort_order' => 40],
        );

        $high = ProjectPriority::query()->firstOrCreate(
            ['slug' => 'high'],
            ['name' => 'High', 'description' => 'High priority project.', 'sort_order' => 10],
        );
        ProjectPriority::query()->firstOrCreate(
            ['slug' => 'medium'],
            ['name' => 'Medium', 'description' => 'Medium priority project.', 'sort_order' => 20],
        );
        ProjectPriority::query()->firstOrCreate(
            ['slug' => 'low'],
            ['name' => 'Low', 'description' => 'Low priority project.', 'sort_order' => 30],
        );

        $publicFunds = FundingSource::query()->firstOrCreate(
            ['slug' => 'public-funds'],
            ['name' => 'Public Funds', 'description' => 'Government-funded project.'],
        );
        FundingSource::query()->firstOrCreate(
            ['slug' => 'development-partner'],
            ['name' => 'Development Partner', 'description' => 'Externally assisted or partner-funded project.'],
        );

        $fiscalYear = FiscalYear::query()->firstOrCreate(
            ['name' => 'FY 2026'],
            ['starts_on' => '2025-07-01', 'ends_on' => '2026-06-30', 'is_active' => true],
        );

        $baselineProject = Project::query()->firstOrCreate(
            ['project_code' => 'CVL-2026-001'],
            [
                'name' => 'CivicLens Baseline Road Improvement',
                'short_name' => 'Baseline Road',
                'slug' => 'civiclens-baseline-road-improvement',
                'description' => 'Baseline seeded project for local development and demonstrations.',
                'agency_id' => $baselineAgency->id,
                'project_category_id' => $transport->id,
                'project_status_id' => $planning->id,
                'project_priority_id' => $high->id,
                'funding_source_id' => $publicFunds->id,
                'fiscal_year_id' => $fiscalYear->id,
                'country_id' => $bangladesh->id,
                'progress_percentage' => 0,
                'planned_start_date' => '2026-01-01',
                'planned_end_date' => '2026-12-31',
                'is_public' => true,
                'is_active' => true,
                'created_by' => $baselineAdmin->id,
                'updated_by' => $baselineAdmin->id,
            ],
        );

        $capitalWorks = BudgetCategory::query()->firstOrCreate(
            ['slug' => 'capital-works'],
            ['name' => 'Capital Works', 'description' => 'Capital construction and infrastructure works.', 'is_active' => true],
        );
        BudgetCategory::query()->firstOrCreate(
            ['slug' => 'operations'],
            ['name' => 'Operations', 'description' => 'Operational and maintenance spending.', 'is_active' => true],
        );

        $developmentBudget = BudgetType::query()->firstOrCreate(
            ['slug' => 'development'],
            ['name' => 'Development', 'description' => 'Development project allocation.', 'is_active' => true],
        );
        BudgetType::query()->firstOrCreate(
            ['slug' => 'operational'],
            ['name' => 'Operational', 'description' => 'Operational budget allocation.', 'is_active' => true],
        );

        $approvedBudget = BudgetStatus::query()->firstOrCreate(
            ['slug' => 'approved'],
            ['name' => 'Approved', 'description' => 'Budget allocation is approved.', 'sort_order' => 10, 'is_active' => true],
        );
        BudgetStatus::query()->firstOrCreate(
            ['slug' => 'draft'],
            ['name' => 'Draft', 'description' => 'Budget is being prepared.', 'sort_order' => 1, 'is_active' => true],
        );
        BudgetStatus::query()->firstOrCreate(
            ['slug' => 'closed'],
            ['name' => 'Closed', 'description' => 'Budget is closed for activity.', 'sort_order' => 99, 'is_active' => true],
        );

        foreach ([
            ['Allocation', 'allocation', 'increase'],
            ['Adjustment', 'adjustment', 'decrease'],
            ['Expenditure', 'expenditure', 'decrease'],
            ['Refund', 'refund', 'increase'],
            ['Transfer', 'transfer', 'decrease'],
        ] as [$name, $slug, $direction]) {
            BudgetTransactionType::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'direction' => $direction, 'description' => "{$name} transaction.", 'is_active' => true],
            );
        }

        Budget::query()->firstOrCreate(
            ['project_id' => $baselineProject->id, 'fiscal_year_id' => $fiscalYear->id],
            [
                'budget_type_id' => $developmentBudget->id,
                'funding_source_id' => $publicFunds->id,
                'budget_category_id' => $capitalWorks->id,
                'budget_status_id' => $approvedBudget->id,
                'original_allocation' => 900000,
                'current_allocation' => 900000,
                'reserved_amount' => 0,
                'committed_amount' => 0,
                'actual_expenditure' => 0,
                'currency' => 'BDT',
                'notes' => 'Baseline budget for CivicLens project finance.',
                'is_active' => true,
            ],
        );
    }
}
