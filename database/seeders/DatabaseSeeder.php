<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\AgencyType;
use App\Models\AnalyticsAlertRule;
use App\Models\AnalyticsSnapshotPeriod;
use App\Models\Budget;
use App\Models\BudgetCategory;
use App\Models\BudgetStatus;
use App\Models\BudgetTransactionType;
use App\Models\BudgetType;
use App\Models\CertificationType;
use App\Models\CitizenReportCategory;
use App\Models\CitizenReportStatus;
use App\Models\ComplianceStatus;
use App\Models\ComplianceType;
use App\Models\ContractorActivityType;
use App\Models\ContractorCategory;
use App\Models\ContractorClassification;
use App\Models\ContractorRegistrationStatus;
use App\Models\ContractorRiskLevel;
use App\Models\Country;
use App\Models\DocumentCategory;
use App\Models\DocumentPermissionType;
use App\Models\DocumentStatus;
use App\Models\DocumentTag;
use App\Models\DocumentType;
use App\Models\DocumentVisibility;
use App\Models\FiscalYear;
use App\Models\FundingSource;
use App\Models\IntelligenceRule;
use App\Models\IntelligenceRuleType;
use App\Models\LicenseType;
use App\Models\OrganizationCompanyType;
use App\Models\OrganizationIndustry;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\ProcurementMethod;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectPriority;
use App\Models\ProjectStatus;
use App\Models\Role;
use App\Models\Tender;
use App\Models\TenderCategory;
use App\Models\TenderStatus;
use App\Models\User;
use App\Services\Search\SearchIndexingService;
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
            ['permission_group_id' => $projectGroup->id, 'name' => 'Manage Procurement', 'slug' => config('civiclens.permissions.procurements_manage'), 'description' => 'Manage tenders, bids, awards, contracts, and procurement timelines.'],
            ['permission_group_id' => $projectGroup->id, 'name' => 'Manage Contractors', 'slug' => config('civiclens.permissions.contractors_manage'), 'description' => 'Manage contractor intelligence, compliance, performance, and vendor records.'],
            ['permission_group_id' => $projectGroup->id, 'name' => 'Manage Documents', 'slug' => config('civiclens.permissions.documents_manage'), 'description' => 'Manage enterprise documents, versions, metadata, and permissions.'],
            ['permission_group_id' => $projectGroup->id, 'name' => 'Manage Search', 'slug' => config('civiclens.permissions.search_manage'), 'description' => 'Use universal search, saved searches, discovery, and search analytics.'],
            ['permission_group_id' => $projectGroup->id, 'name' => 'View Analytics', 'slug' => config('civiclens.permissions.analytics_view'), 'description' => 'View platform analytics dashboards.'],
            ['permission_group_id' => $projectGroup->id, 'name' => 'Manage Analytics', 'slug' => config('civiclens.permissions.analytics_manage'), 'description' => 'Generate analytics snapshots, reports, alerts, and dashboard states.'],
            ['permission_group_id' => $projectGroup->id, 'name' => 'Manage Intelligence', 'slug' => config('civiclens.permissions.intelligence_manage'), 'description' => 'Manage rule-based intelligence indicators, evidence review, and processing readiness.'],
            ['permission_group_id' => $projectGroup->id, 'name' => 'Submit Reports', 'slug' => config('civiclens.permissions.reports_submit'), 'description' => 'Submit civic reports and field updates.'],
            ['permission_group_id' => $projectGroup->id, 'name' => 'Manage Citizen Reports', 'slug' => config('civiclens.permissions.citizen_reports_manage'), 'description' => 'Moderate public citizen reports and status workflows.'],
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
            config('civiclens.permissions.procurements_manage'),
            config('civiclens.permissions.contractors_manage'),
            config('civiclens.permissions.documents_manage'),
            config('civiclens.permissions.search_manage'),
            config('civiclens.permissions.analytics_view'),
            config('civiclens.permissions.intelligence_manage'),
            config('civiclens.permissions.citizen_reports_manage'),
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

        foreach ([
            ['name' => 'Project Risk', 'slug' => 'project-risk', 'description' => 'Project schedule and delivery risk indicators.', 'sort_order' => 10],
            ['name' => 'Financial Risk', 'slug' => 'financial-risk', 'description' => 'Budget utilization and overrun indicators.', 'sort_order' => 20],
            ['name' => 'Procurement Risk', 'slug' => 'procurement-risk', 'description' => 'Tender and bidding risk indicators.', 'sort_order' => 30],
            ['name' => 'Contractor Risk', 'slug' => 'contractor-risk', 'description' => 'Contractor compliance and status indicators.', 'sort_order' => 40],
            ['name' => 'Document Readiness', 'slug' => 'document-readiness', 'description' => 'Metadata and OCR readiness indicators.', 'sort_order' => 50],
            ['name' => 'Platform Operations', 'slug' => 'platform-operations', 'description' => 'Search, analytics, and platform processing indicators.', 'sort_order' => 60],
        ] as $type) {
            IntelligenceRuleType::query()->firstOrCreate(
                ['slug' => $type['slug']],
                ['name' => $type['name'], 'description' => $type['description'], 'sort_order' => $type['sort_order'], 'is_active' => true],
            );
        }

        $ruleTypes = IntelligenceRuleType::query()->pluck('id', 'slug');

        foreach ([
            ['type' => 'project-risk', 'name' => 'Project delay risk', 'slug' => 'project-delay-risk', 'module' => 'projects', 'category' => 'delay', 'severity_default' => 'warning', 'thresholds' => ['days_overdue' => 1, 'max_progress' => 90, 'warning' => 30, 'critical' => 90]],
            ['type' => 'financial-risk', 'name' => 'Budget overrun risk', 'slug' => 'budget-overrun-risk', 'module' => 'finance', 'category' => 'budget', 'severity_default' => 'critical', 'thresholds' => ['warning' => 90, 'critical' => 100]],
            ['type' => 'financial-risk', 'name' => 'Low budget utilization', 'slug' => 'low-budget-utilization', 'module' => 'finance', 'category' => 'budget', 'severity_default' => 'info', 'thresholds' => ['max_utilization' => 10, 'warning' => 50, 'critical' => 90]],
            ['type' => 'procurement-risk', 'name' => 'Procurement single bid risk', 'slug' => 'procurement-single-bid-risk', 'module' => 'procurement', 'category' => 'competition', 'severity_default' => 'warning', 'thresholds' => ['warning' => 50, 'critical' => 90]],
            ['type' => 'procurement-risk', 'name' => 'Repeat winner concentration', 'slug' => 'procurement-repeat-winner-concentration', 'module' => 'procurement', 'category' => 'award-concentration', 'severity_default' => 'warning', 'thresholds' => ['warning' => 2, 'critical' => 4]],
            ['type' => 'contractor-risk', 'name' => 'Contractor compliance expiry', 'slug' => 'contractor-compliance-expiry', 'module' => 'contractors', 'category' => 'compliance', 'severity_default' => 'warning', 'thresholds' => ['warning' => 50, 'critical' => 90]],
            ['type' => 'contractor-risk', 'name' => 'Contractor blacklist signal', 'slug' => 'contractor-blacklist-signal', 'module' => 'contractors', 'category' => 'status', 'severity_default' => 'critical', 'thresholds' => ['warning' => 50, 'critical' => 90]],
            ['type' => 'document-readiness', 'name' => 'Document missing metadata', 'slug' => 'document-missing-metadata', 'module' => 'documents', 'category' => 'metadata', 'severity_default' => 'info', 'thresholds' => ['warning' => 50, 'critical' => 90]],
            ['type' => 'document-readiness', 'name' => 'Document pending OCR readiness', 'slug' => 'document-pending-ocr-readiness', 'module' => 'documents', 'category' => 'ocr', 'severity_default' => 'info', 'thresholds' => ['warning' => 50, 'critical' => 90]],
            ['type' => 'project-risk', 'name' => 'Citizen report cluster', 'slug' => 'citizen-report-cluster', 'module' => 'public', 'category' => 'citizen-reporting', 'severity_default' => 'warning', 'thresholds' => ['warning' => 2, 'critical' => 5]],
            ['type' => 'platform-operations', 'name' => 'Search indexing failure', 'slug' => 'search-indexing-failure', 'module' => 'search', 'category' => 'indexing', 'severity_default' => 'warning', 'thresholds' => ['warning' => 50, 'critical' => 90]],
            ['type' => 'platform-operations', 'name' => 'Analytics alert escalation', 'slug' => 'analytics-alert-escalation', 'module' => 'analytics', 'category' => 'alert', 'severity_default' => 'warning', 'thresholds' => ['warning' => 50, 'critical' => 90]],
        ] as $rule) {
            IntelligenceRule::query()->firstOrCreate(
                ['slug' => $rule['slug']],
                [
                    'intelligence_rule_type_id' => $ruleTypes[$rule['type']],
                    'name' => $rule['name'],
                    'module' => $rule['module'],
                    'category' => $rule['category'],
                    'severity_default' => $rule['severity_default'],
                    'thresholds' => $rule['thresholds'],
                    'configuration' => [],
                    'version' => '1.0.0',
                    'is_active' => true,
                    'created_by' => $baselineAdmin->id,
                    'updated_by' => $baselineAdmin->id,
                ],
            );
        }

        foreach ([
            ['name' => 'Daily', 'slug' => 'daily', 'sort_order' => 10],
            ['name' => 'Weekly', 'slug' => 'weekly', 'sort_order' => 20],
            ['name' => 'Monthly', 'slug' => 'monthly', 'sort_order' => 30],
            ['name' => 'Quarterly', 'slug' => 'quarterly', 'sort_order' => 40],
            ['name' => 'Yearly', 'slug' => 'yearly', 'sort_order' => 50],
        ] as $period) {
            AnalyticsSnapshotPeriod::query()->firstOrCreate(
                ['slug' => $period['slug']],
                ['name' => $period['name'], 'sort_order' => $period['sort_order'], 'is_active' => true],
            );
        }

        foreach ([
            [
                'name' => 'Budget utilization warning',
                'slug' => 'budget-utilization-warning',
                'category' => 'finance',
                'metric_key' => 'finance.budget_utilization',
                'operator' => '>=',
                'threshold' => 90,
                'severity' => 'warning',
                'message_template' => ':metric reached :value%, above the :threshold% threshold.',
                'sort_order' => 10,
            ],
            [
                'name' => 'Delayed projects watch',
                'slug' => 'delayed-projects-watch',
                'category' => 'projects',
                'metric_key' => 'projects.delayed',
                'operator' => '>=',
                'threshold' => 1,
                'severity' => 'warning',
                'message_template' => ':metric reached :value, above the :threshold threshold.',
                'sort_order' => 20,
            ],
        ] as $rule) {
            AnalyticsAlertRule::query()->firstOrCreate(
                ['slug' => $rule['slug']],
                array_merge($rule, ['is_active' => true]),
            );
        }

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

        $baselineBudget = Budget::query()->firstOrCreate(
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

        $openTendering = ProcurementMethod::query()->firstOrCreate(
            ['slug' => 'open-tendering'],
            ['name' => 'Open Tendering', 'description' => 'Competitive open tendering process.', 'is_active' => true],
        );
        ProcurementMethod::query()->firstOrCreate(
            ['slug' => 'limited-tendering'],
            ['name' => 'Limited Tendering', 'description' => 'Limited competitive procurement process.', 'is_active' => true],
        );
        ProcurementMethod::query()->firstOrCreate(
            ['slug' => 'direct-procurement'],
            ['name' => 'Direct Procurement', 'description' => 'Direct procurement under approved rules.', 'is_active' => true],
        );

        $works = TenderCategory::query()->firstOrCreate(
            ['slug' => 'works'],
            ['name' => 'Works', 'description' => 'Civil works and construction procurement.', 'is_active' => true],
        );
        TenderCategory::query()->firstOrCreate(
            ['slug' => 'goods'],
            ['name' => 'Goods', 'description' => 'Goods and equipment procurement.', 'is_active' => true],
        );
        TenderCategory::query()->firstOrCreate(
            ['slug' => 'services'],
            ['name' => 'Services', 'description' => 'Consulting and non-consulting services.', 'is_active' => true],
        );

        $draftTender = TenderStatus::query()->firstOrCreate(
            ['slug' => 'draft'],
            ['name' => 'Draft', 'description' => 'Tender is being prepared.', 'sort_order' => 1, 'is_active' => true],
        );
        TenderStatus::query()->firstOrCreate(
            ['slug' => 'published'],
            ['name' => 'Published', 'description' => 'Tender is open for bidder participation.', 'sort_order' => 10, 'is_active' => true],
        );
        TenderStatus::query()->firstOrCreate(
            ['slug' => 'closed'],
            ['name' => 'Closed', 'description' => 'Tender submission window has closed.', 'sort_order' => 20, 'is_active' => true],
        );
        TenderStatus::query()->firstOrCreate(
            ['slug' => 'awarded'],
            ['name' => 'Awarded', 'description' => 'Tender has an award decision.', 'sort_order' => 30, 'is_active' => true],
        );

        $baselineTender = Tender::query()->firstOrCreate(
            ['tender_number' => 'CVL-TDR-2026-001'],
            [
                'project_id' => $baselineProject->id,
                'budget_id' => $baselineBudget->id,
                'agency_id' => $baselineAgency->id,
                'procurement_method_id' => $openTendering->id,
                'tender_category_id' => $works->id,
                'tender_status_id' => $draftTender->id,
                'title' => 'Baseline Road Improvement Works Tender',
                'slug' => 'baseline-road-improvement-works-tender',
                'description' => 'Baseline procurement tender for CivicLens development and demonstrations.',
                'closing_at' => '2026-09-30 17:00:00',
                'is_public' => true,
                'is_active' => true,
            ],
        );

        foreach ([
            ['Limited Company', 'limited-company', 'Legally registered limited liability company.'],
            ['Partnership', 'partnership', 'Registered partnership organization.'],
            ['Sole Proprietorship', 'sole-proprietorship', 'Single owner contractor organization.'],
        ] as [$name, $slug, $description]) {
            OrganizationCompanyType::query()->firstOrCreate(['slug' => $slug], ['name' => $name, 'description' => $description, 'is_active' => true]);
        }

        foreach ([
            ['Construction', 'construction', 'Infrastructure and civil construction.'],
            ['Engineering', 'engineering', 'Engineering design and delivery.'],
            ['Public Utilities', 'public-utilities', 'Utility and public service delivery.'],
        ] as [$name, $slug, $description]) {
            OrganizationIndustry::query()->firstOrCreate(['slug' => $slug], ['name' => $name, 'description' => $description, 'is_active' => true]);
        }

        foreach ([
            [ContractorCategory::class, 'Civil Works', 'civil-works'],
            [ContractorCategory::class, 'Goods Supplier', 'goods-supplier'],
            [ContractorCategory::class, 'Consulting Services', 'consulting-services'],
            [ContractorClassification::class, 'Class A', 'class-a'],
            [ContractorClassification::class, 'Class B', 'class-b'],
            [ContractorClassification::class, 'Class C', 'class-c'],
            [ContractorRegistrationStatus::class, 'Registered', 'registered'],
            [ContractorRegistrationStatus::class, 'Pending Review', 'pending-review'],
            [ContractorRegistrationStatus::class, 'Expired', 'expired'],
            [ContractorRiskLevel::class, 'Low', 'low'],
            [ContractorRiskLevel::class, 'Medium', 'medium'],
            [ContractorRiskLevel::class, 'High', 'high'],
            [LicenseType::class, 'Trade License', 'trade-license'],
            [LicenseType::class, 'Construction License', 'construction-license'],
            [CertificationType::class, 'ISO', 'iso'],
            [CertificationType::class, 'Environmental', 'environmental'],
            [CertificationType::class, 'Safety', 'safety'],
            [CertificationType::class, 'Engineering', 'engineering'],
            [CertificationType::class, 'Government Certification', 'government-certification'],
            [ComplianceType::class, 'Tax Compliance', 'tax-compliance'],
            [ComplianceType::class, 'Labor Compliance', 'labor-compliance'],
            [ComplianceType::class, 'Environmental Compliance', 'environmental-compliance'],
            [ComplianceType::class, 'Safety Compliance', 'safety-compliance'],
            [ComplianceType::class, 'Legal Compliance', 'legal-compliance'],
            [ComplianceStatus::class, 'Compliant', 'compliant'],
            [ComplianceStatus::class, 'Failed', 'failed'],
            [ComplianceStatus::class, 'Under Review', 'under-review'],
            [ContractorActivityType::class, 'Registered', 'registered'],
            [ContractorActivityType::class, 'License Updated', 'license-updated'],
            [ContractorActivityType::class, 'Suspended', 'suspended'],
            [ContractorActivityType::class, 'Reinstated', 'reinstated'],
            [ContractorActivityType::class, 'Won Tender', 'won-tender'],
            [ContractorActivityType::class, 'Completed Contract', 'completed-contract'],
            [ContractorActivityType::class, 'Compliance Inspection', 'compliance-inspection'],
        ] as [$model, $name, $slug]) {
            $model::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => "{$name} contractor reference value.", 'is_active' => true],
            );
        }

        foreach ([
            [DocumentType::class, 'Contract', 'contract'],
            [DocumentType::class, 'Tender Notice', 'tender-notice'],
            [DocumentType::class, 'Drawing', 'drawing'],
            [DocumentType::class, 'Invoice', 'invoice'],
            [DocumentType::class, 'Progress Report', 'progress-report'],
            [DocumentType::class, 'Inspection Report', 'inspection-report'],
            [DocumentType::class, 'Completion Certificate', 'completion-certificate'],
            [DocumentType::class, 'Meeting Minutes', 'meeting-minutes'],
            [DocumentType::class, 'Letter', 'letter'],
            [DocumentType::class, 'Memo', 'memo'],
            [DocumentCategory::class, 'Procurement', 'procurement'],
            [DocumentCategory::class, 'Finance', 'finance'],
            [DocumentCategory::class, 'Engineering', 'engineering'],
            [DocumentCategory::class, 'Compliance', 'compliance'],
            [DocumentStatus::class, 'Draft', 'draft'],
            [DocumentStatus::class, 'Active', 'active'],
            [DocumentStatus::class, 'Archived', 'archived'],
            [DocumentStatus::class, 'Superseded', 'superseded'],
            [DocumentVisibility::class, 'Private', 'private'],
            [DocumentVisibility::class, 'Internal', 'internal'],
            [DocumentVisibility::class, 'Agency', 'agency'],
            [DocumentVisibility::class, 'Public', 'public'],
            [DocumentVisibility::class, 'Role Specific', 'role-specific'],
            [DocumentPermissionType::class, 'View', 'view'],
            [DocumentPermissionType::class, 'Download', 'download'],
            [DocumentPermissionType::class, 'Manage', 'manage'],
        ] as [$model, $name, $slug]) {
            $model::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => "{$name} document reference value.", 'is_active' => true],
            );
        }

        foreach ([
            ['Baseline', 'baseline'],
            ['Confidential', 'confidential'],
            ['Public Release', 'public-release'],
            ['Audit', 'audit'],
        ] as [$name, $slug]) {
            DocumentTag::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => "{$name} document tag."],
            );
        }

        foreach ([
            ['Safety Concern', 'safety-concern', 10],
            ['Delayed Work', 'delayed-work', 20],
            ['Budget Concern', 'budget-concern', 30],
            ['Procurement Concern', 'procurement-concern', 40],
            ['Missing Information', 'missing-information', 50],
            ['Document Request', 'document-request', 60],
            ['General Feedback', 'general-feedback', 70],
        ] as [$name, $slug, $sortOrder]) {
            CitizenReportCategory::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => "{$name} citizen report category.", 'is_active' => true, 'sort_order' => $sortOrder],
            );
        }

        foreach ([
            ['Submitted', 'submitted', true, false, 10],
            ['In Review', 'in-review', false, false, 20],
            ['Needs Information', 'needs-information', false, false, 30],
            ['Accepted', 'accepted', false, false, 40],
            ['Resolved', 'resolved', false, true, 50],
            ['Dismissed', 'dismissed', false, true, 60],
            ['Archived', 'archived', false, true, 70],
        ] as [$name, $slug, $isDefault, $isTerminal, $sortOrder]) {
            CitizenReportStatus::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'description' => "{$name} citizen report status.",
                    'is_default' => $isDefault,
                    'is_terminal' => $isTerminal,
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                ],
            );
        }

        $indexing = app(SearchIndexingService::class);

        foreach ([$bangladesh, $baselineAgency, $baselineProject, $baselineBudget, $baselineTender] as $searchableRecord) {
            $indexing->index($searchableRecord);
        }
    }
}
