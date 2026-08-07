<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\AgencyType;
use App\Models\AnalyticsAlert;
use App\Models\AnalyticsAlertRule;
use App\Models\AnalyticsReport;
use App\Models\AnalyticsSnapshot;
use App\Models\AnalyticsSnapshotPeriod;
use App\Models\Budget;
use App\Models\BudgetCategory;
use App\Models\BudgetStatus;
use App\Models\BudgetType;
use App\Models\CitizenReport;
use App\Models\CitizenReportCategory;
use App\Models\CitizenReportStatus;
use App\Models\CivicIntelligenceRun;
use App\Models\ContractorCategory;
use App\Models\ContractorClassification;
use App\Models\ContractorProfile;
use App\Models\ContractorRegistrationStatus;
use App\Models\ContractorRiskLevel;
use App\Models\Country;
use App\Models\District;
use App\Models\Division;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentStatus;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\DocumentVisibility;
use App\Models\FiscalYear;
use App\Models\FundingSource;
use App\Models\IntelligenceEvidence;
use App\Models\IntelligenceIndicator;
use App\Models\IntelligenceRule;
use App\Models\Organization;
use App\Models\OrganizationCompanyType;
use App\Models\OrganizationIndustry;
use App\Models\ProcurementMethod;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectPriority;
use App\Models\ProjectStatus;
use App\Models\Role;
use App\Models\Tender;
use App\Models\TenderCategory;
use App\Models\TenderStatus;
use App\Models\Upazila;
use App\Models\User;
use App\Services\Search\SearchIndexingService;
use Database\Seeders\Concerns\GuardsSeedingEnvironment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoCivicLensSeeder extends Seeder
{
    use GuardsSeedingEnvironment;

    public function run(): void
    {
        $this->guardSeedingEnvironment();

        $adminRole = Role::query()->where('slug', 'admin')->firstOrFail();
        $staffRole = Role::query()->where('slug', 'staff')->firstOrFail();
        $citizenRole = Role::query()->where('slug', 'citizen')->firstOrFail();

        $admin = $this->user('admin@civiclens.test', 'CivicLens Demo Administrator', $adminRole);
        $staff = $this->user('staff@civiclens.test', 'CivicLens Program Officer', $staffRole);
        $citizen = $this->user('citizen@civiclens.test', 'CivicLens Citizen Reviewer', $citizenRole);

        $country = Country::query()->where('iso2', 'BD')->firstOrFail();
        $division = Division::query()->firstOrCreate(
            ['country_id' => $country->id, 'name' => 'Dhaka'],
            ['code' => 'DHK', 'latitude' => 23.8103, 'longitude' => 90.4125],
        );
        $district = District::query()->firstOrCreate(
            ['division_id' => $division->id, 'name' => 'Dhaka'],
            ['code' => 'DHK-D', 'latitude' => 23.8103, 'longitude' => 90.4125],
        );
        $upazila = Upazila::query()->firstOrCreate(
            ['district_id' => $district->id, 'name' => 'Tejgaon'],
            ['code' => 'TG', 'latitude' => 23.7587, 'longitude' => 90.3916],
        );

        $agencyType = AgencyType::query()->where('slug', 'department')->firstOrFail();
        $agencies = collect([
            ['urban-transport-authority', 'Urban Transport Authority', 'UTA', 'Road corridors, footpaths, and safer junction delivery.'],
            ['public-health-engineering-office', 'Public Health Engineering Office', 'PHEO', 'Water supply, drainage, and sanitation delivery.'],
            ['digital-services-directorate', 'Digital Services Directorate', 'DSD', 'Digital public-service modernization.'],
        ])->map(fn (array $agency): Agency => Agency::query()->firstOrCreate(
            ['slug' => $agency[0]],
            [
                'agency_type_id' => $agencyType->id,
                'country_id' => $country->id,
                'division_id' => $division->id,
                'district_id' => $district->id,
                'name' => $agency[1],
                'short_name' => $agency[2],
                'status' => 'active',
                'description' => $agency[3],
            ],
        ));

        foreach ([$admin, $staff] as $user) {
            $user->agencies()->syncWithoutDetaching($agencies->pluck('id')->mapWithKeys(fn (int $id): array => [$id => ['relationship' => 'assigned']])->all());
        }

        $categoryBySlug = ProjectCategory::query()->get()->keyBy('slug');
        $statusBySlug = ProjectStatus::query()->get()->keyBy('slug');
        $priorityBySlug = ProjectPriority::query()->get()->keyBy('slug');
        $funding = FundingSource::query()->where('slug', 'public-funds')->firstOrFail();
        $fy = FiscalYear::query()->where('name', 'FY 2026')->firstOrFail();
        $budgetType = BudgetType::query()->where('slug', 'development')->firstOrFail();
        $budgetCategory = BudgetCategory::query()->where('slug', 'capital-works')->firstOrFail();
        $budgetStatus = BudgetStatus::query()->where('slug', 'approved')->firstOrFail();

        $projects = collect([
            ['CL-DEMO-001', 'Eastern Bus Corridor Signal Upgrade', 'eastern-bus-corridor-signal-upgrade', 'Adaptive signals, accessible crossings, and bus-priority lanes across a congested eastern corridor.', 'transport', 'in-progress', 'high', 68, 6800000, 6120000, $agencies[0]],
            ['CL-DEMO-002', 'Mirpur Water Resilience Package', 'mirpur-water-resilience-package', 'Drainage rehabilitation, pump upgrades, and household water-point improvements.', 'water-sanitation', 'in-progress', 'high', 42, 4500000, 1880000, $agencies[1]],
            ['CL-DEMO-003', 'Ward Health Clinic Modernization', 'ward-health-clinic-modernization', 'Renovation, solar backup, waiting-area safety, and digital queue management for primary clinics.', 'public-buildings', 'planning', 'medium', 12, 2200000, 160000, $agencies[1]],
            ['CL-DEMO-004', 'Civic Permit Service Portal', 'civic-permit-service-portal', 'Online permit intake, internal workflow dashboards, and public status tracking.', 'public-buildings', 'completed', 'medium', 100, 1250000, 1220000, $agencies[2]],
            ['CL-DEMO-005', 'Safe School Streets Pilot', 'safe-school-streets-pilot', 'Traffic calming, safer crossings, signage, and community reporting near schools.', 'transport', 'suspended', 'high', 31, 1750000, 780000, $agencies[0]],
        ])->map(function (array $row) use ($admin, $budgetCategory, $budgetStatus, $budgetType, $categoryBySlug, $country, $district, $division, $funding, $fy, $priorityBySlug, $statusBySlug, $upazila): Project {
            $project = Project::query()->updateOrCreate(
                ['project_code' => $row[0]],
                [
                    'name' => $row[1],
                    'short_name' => Str::headline(str_replace('-', ' ', $row[2])),
                    'slug' => $row[2],
                    'description' => $row[3],
                    'agency_id' => $row[10]->id,
                    'project_category_id' => $categoryBySlug[$row[4]]->id,
                    'project_status_id' => $statusBySlug[$row[5]]->id,
                    'project_priority_id' => $priorityBySlug[$row[6]]->id,
                    'funding_source_id' => $funding->id,
                    'fiscal_year_id' => $fy->id,
                    'country_id' => $country->id,
                    'division_id' => $division->id,
                    'district_id' => $district->id,
                    'upazila_id' => $upazila->id,
                    'latitude' => 23.7587 + ($row[7] / 100000),
                    'longitude' => 90.3916 + ($row[7] / 100000),
                    'progress_percentage' => $row[7],
                    'planned_start_date' => now()->subMonths(8)->toDateString(),
                    'actual_start_date' => now()->subMonths(7)->toDateString(),
                    'planned_end_date' => $row[5] === 'completed' ? now()->subMonths(1)->toDateString() : now()->addMonths(5)->toDateString(),
                    'actual_end_date' => $row[5] === 'completed' ? now()->subWeeks(3)->toDateString() : null,
                    'is_public' => true,
                    'is_active' => true,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );

            Budget::query()->updateOrCreate(
                ['project_id' => $project->id, 'fiscal_year_id' => $fy->id],
                [
                    'budget_type_id' => $budgetType->id,
                    'funding_source_id' => $funding->id,
                    'budget_category_id' => $budgetCategory->id,
                    'budget_status_id' => $budgetStatus->id,
                    'original_allocation' => $row[8],
                    'current_allocation' => $row[8],
                    'reserved_amount' => $row[8] * 0.08,
                    'committed_amount' => $row[8] * 0.22,
                    'actual_expenditure' => $row[9],
                    'currency' => 'BDT',
                    'notes' => 'Demo allocation used for CivicLens v1 dashboard and risk walkthroughs.',
                    'is_active' => true,
                ],
            );

            return $project;
        });

        $companyType = OrganizationCompanyType::query()->where('slug', 'limited-company')->firstOrFail();
        $industry = OrganizationIndustry::query()->where('slug', 'construction')->firstOrFail();
        $contractorCategory = ContractorCategory::query()->where('slug', 'civil-works')->firstOrFail();
        $classification = ContractorClassification::query()->where('slug', 'class-a')->firstOrFail();
        $registration = ContractorRegistrationStatus::query()->where('slug', 'registered')->firstOrFail();
        $riskLevels = ContractorRiskLevel::query()->get()->keyBy('slug');

        $organizations = collect([
            ['NORTHSTAR-INFRA-2026', 'Northstar Infrastructure Ltd.', 'low'],
            ['RIVERBEND-BUILDERS-2026', 'Riverbend Builders Consortium', 'medium'],
            ['CIVICGRID-SYSTEMS-2026', 'CivicGrid Systems Ltd.', 'low'],
            ['DELTA-URBAN-WORKS-2026', 'Delta Urban Works Ltd.', 'high'],
        ])->map(function (array $row) use ($admin, $classification, $companyType, $contractorCategory, $country, $district, $division, $industry, $registration, $riskLevels): Organization {
            $organization = Organization::query()->updateOrCreate(
                ['registration_number' => $row[0]],
                [
                    'organization_company_type_id' => $companyType->id,
                    'organization_industry_id' => $industry->id,
                    'country_id' => $country->id,
                    'division_id' => $division->id,
                    'district_id' => $district->id,
                    'legal_name' => $row[1],
                    'trade_name' => Str::before($row[1], ' Ltd.'),
                    'tax_identification_number' => 'TIN-'.$row[0],
                    'website' => 'https://example.gov/vendors/'.Str::slug($row[1]),
                    'email' => Str::slug($row[1]).'@example.test',
                    'phone' => '+8801700000000',
                    'headquarters_address' => 'Demo civic procurement registry address, Dhaka',
                    'status' => 'active',
                    'established_date' => now()->subYears(8)->toDateString(),
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );

            ContractorProfile::query()->updateOrCreate(
                ['organization_id' => $organization->id],
                [
                    'contractor_category_id' => $contractorCategory->id,
                    'contractor_classification_id' => $classification->id,
                    'contractor_registration_status_id' => $registration->id,
                    'contractor_risk_level_id' => $riskLevels[$row[2]]->id,
                    'is_active' => true,
                    'is_suspended' => $row[2] === 'high',
                    'is_blacklisted' => false,
                    'is_public' => true,
                ],
            );

            return $organization;
        });

        $method = ProcurementMethod::query()->where('slug', 'open-tendering')->firstOrFail();
        $category = TenderCategory::query()->where('slug', 'works')->firstOrFail();
        $published = TenderStatus::query()->where('slug', 'published')->firstOrFail();
        $closed = TenderStatus::query()->where('slug', 'closed')->firstOrFail();
        $awarded = TenderStatus::query()->where('slug', 'awarded')->firstOrFail();

        foreach ($projects as $index => $project) {
            $budget = $project->budgets()->firstOrFail();
            Tender::query()->updateOrCreate(
                ['tender_number' => 'CL-TDR-2026-'.str_pad((string) ($index + 10), 3, '0', STR_PAD_LEFT)],
                [
                    'project_id' => $project->id,
                    'budget_id' => $budget->id,
                    'agency_id' => $project->agency_id,
                    'procurement_method_id' => $method->id,
                    'tender_category_id' => $category->id,
                    'tender_status_id' => [$published->id, $closed->id, $awarded->id][$index % 3],
                    'title' => $project->name.' Procurement Package',
                    'slug' => $project->slug.'-procurement-package',
                    'description' => 'Demo procurement lifecycle record with public-safe tender metadata and audit-ready status.',
                    'published_at' => now()->subDays(45 - $index)->toDateTimeString(),
                    'closing_at' => now()->addDays(18 - $index)->setTime(17, 0)->toDateTimeString(),
                    'closed_at' => $index > 1 ? now()->subDays(6)->toDateTimeString() : null,
                    'is_public' => true,
                    'is_active' => true,
                ],
            );
        }

        $this->seedDocuments($projects, $admin);
        $this->seedCitizenReports($projects, $agencies, $citizen, $admin);
        $this->seedAnalytics($admin);
        $this->seedIntegrity($projects, $organizations, $admin);

        $indexing = app(SearchIndexingService::class);
        $projects->each(fn (Project $project) => $indexing->index($project));
        Budget::query()->whereIn('project_id', $projects->pluck('id'))->get()->each(fn (Budget $budget) => $indexing->index($budget));
        Tender::query()->whereIn('project_id', $projects->pluck('id'))->get()->each(fn (Tender $tender) => $indexing->index($tender));
        $organizations->each(fn (Organization $organization) => $indexing->index($organization));
        Document::query()->where('title', 'like', 'Demo:%')->get()->each(fn (Document $document) => $indexing->index($document));
        IntelligenceIndicator::query()->where('title', 'like', 'Demo:%')->get()->each(fn (IntelligenceIndicator $indicator) => $indexing->index($indicator));
    }

    private function user(string $email, string $name, Role $role): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $this->seedingPassword(),
                'email_verified_at' => now(),
                'is_active' => true,
                'locked_at' => null,
                'notification_preferences' => ['email_reports' => true, 'security_alerts' => true, 'appearance' => 'system'],
            ],
        );

        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function seedDocuments($projects, User $admin): void
    {
        $type = DocumentType::query()->where('slug', 'progress-report')->firstOrFail();
        $category = DocumentCategory::query()->where('slug', 'procurement')->firstOrFail();
        $status = DocumentStatus::query()->where('slug', 'active')->firstOrFail();
        $visibility = DocumentVisibility::query()->where('slug', 'public')->firstOrFail();

        foreach ($projects as $index => $project) {
            $document = Document::query()->updateOrCreate(
                ['title' => 'Demo: '.$project->name.' public progress brief'],
                [
                    'uuid' => (string) Str::uuid(),
                    'document_type_id' => $type->id,
                    'document_category_id' => $category->id,
                    'document_status_id' => $status->id,
                    'document_visibility_id' => $visibility->id,
                    'description' => 'Public-safe demo brief covering procurement, milestones, financial status, and integrity evidence context.',
                    'original_filename' => 'demo-'.$project->slug.'-brief.pdf',
                    'stored_filename' => Str::uuid().'.pdf',
                    'storage_disk' => 'local',
                    'storage_path' => 'documents/demo-'.$project->slug.'.pdf',
                    'file_extension' => 'pdf',
                    'mime_type' => 'application/pdf',
                    'checksum' => hash('sha256', $project->project_code.'-document'),
                    'file_size' => 180000 + ($index * 12000),
                    'page_count' => 6 + $index,
                    'language' => 'en',
                    'owner_id' => $admin->id,
                    'uploaded_by' => $admin->id,
                    'version_number' => 1,
                    'ocr_status' => $index % 2 === 0 ? 'ready' : 'pending',
                    'index_status' => 'indexed',
                    'preview_status' => 'ready',
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );

            $disk = Storage::disk($document->storage_disk);

            if (! $disk->exists($document->storage_path)) {
                $disk->put($document->storage_path, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<<>>\n%%EOF\n");
            }

            DocumentVersion::query()->updateOrCreate(
                ['document_id' => $document->id, 'version_number' => 1],
                [
                    'original_filename' => $document->original_filename,
                    'stored_filename' => $document->stored_filename,
                    'storage_disk' => $document->storage_disk,
                    'storage_path' => $document->storage_path,
                    'file_extension' => $document->file_extension,
                    'mime_type' => $document->mime_type,
                    'checksum' => $document->checksum,
                    'file_size' => $document->file_size,
                    'uploaded_by' => $admin->id,
                    'reason' => 'Initial public demo version',
                    'is_current' => true,
                ],
            );

            $disk = Storage::disk($document->storage_disk);

            if (! $disk->exists($document->storage_path)) {
                $disk->put($document->storage_path, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");
            }

            DB::table('documentables')->updateOrInsert(
                ['document_id' => $document->id, 'documentable_type' => Project::class, 'documentable_id' => $project->id],
                ['relationship_type' => 'public_progress_brief', 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    private function seedCitizenReports($projects, $agencies, User $citizen, User $admin): void
    {
        $category = CitizenReportCategory::query()->where('slug', 'delayed-work')->firstOrFail();
        $submitted = CitizenReportStatus::query()->where('slug', 'submitted')->firstOrFail();
        $review = CitizenReportStatus::query()->where('slug', 'in-review')->firstOrFail();

        foreach ($projects as $index => $project) {
            CitizenReport::query()->updateOrCreate(
                ['title' => 'Demo: Field observation for '.$project->short_name],
                [
                    'public_uuid' => (string) Str::uuid(),
                    'citizen_report_category_id' => $category->id,
                    'citizen_report_status_id' => $index % 2 === 0 ? $review->id : $submitted->id,
                    'submitter_id' => $citizen->id,
                    'assigned_to' => $admin->id,
                    'project_id' => $project->id,
                    'agency_id' => $agencies[$index % $agencies->count()]->id,
                    'country_id' => $project->country_id,
                    'division_id' => $project->division_id,
                    'district_id' => $project->district_id,
                    'upazila_id' => $project->upazila_id,
                    'description' => 'Demo citizen observation for validating moderation, analytics, and integrity correlation screens.',
                    'location_text' => 'Demo corridor segment '.$index.', Dhaka',
                    'contact_preference' => 'email',
                    'submitted_at' => now()->subDays(12 - $index),
                ],
            );
        }
    }

    private function seedAnalytics(User $admin): void
    {
        $period = AnalyticsSnapshotPeriod::query()->where('slug', 'daily')->firstOrFail();
        $rule = AnalyticsAlertRule::query()->where('slug', 'budget-utilization-warning')->firstOrFail();

        foreach (['executive', 'procurement', 'integrity'] as $index => $dashboard) {
            AnalyticsSnapshot::query()->updateOrCreate(
                ['dashboard' => $dashboard, 'snapshot_date' => now()->subDays($index)->toDateString(), 'filter_hash' => hash('sha256', $dashboard)],
                [
                    'analytics_snapshot_period_id' => $period->id,
                    'filters' => ['dashboard' => $dashboard, 'demo' => true],
                    'metrics' => [
                        'projects.active' => ['value' => Project::query()->count(), 'label' => 'Active projects'],
                        'procurement.tenders' => ['value' => Tender::query()->count(), 'label' => 'Tender volume'],
                        'integrity.pending' => ['value' => IntelligenceIndicator::query()->where('status', 'pending')->count(), 'label' => 'Pending review'],
                    ],
                    'charts' => ['trend' => ['type' => 'bar', 'label' => Str::headline($dashboard)]],
                    'insights' => ['Demo snapshot prepared for dashboard inspection.'],
                    'generated_by' => $admin->id,
                    'generated_at' => now()->subHours($index + 1),
                ],
            );

            AnalyticsReport::query()->updateOrCreate(
                ['name' => 'Demo '.Str::headline($dashboard).' Report', 'dashboard' => $dashboard],
                [
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $admin->id,
                    'format' => $index === 0 ? 'pdf' : 'csv',
                    'status' => 'generated',
                    'filters' => ['demo' => true],
                    'payload' => ['summary' => 'Demo report payload for '.$dashboard],
                    'generated_at' => now()->subHours($index + 2),
                    'expires_at' => now()->addDays(14),
                ],
            );
        }

        AnalyticsAlert::query()->updateOrCreate(
            ['title' => 'Demo: Budget utilization requires review'],
            [
                'analytics_alert_rule_id' => $rule->id,
                'message' => 'Demo finance signal crossed the configured budget-utilization threshold and is ready for operator review.',
                'severity' => 'warning',
                'status' => 'open',
                'triggered_value' => 94.5,
                'context' => ['dashboard' => 'executive', 'demo' => true],
                'triggered_at' => now()->subHours(6),
            ],
        );
    }

    private function seedIntegrity($projects, $organizations, User $admin): void
    {
        $run = CivicIntelligenceRun::query()->updateOrCreate(
            ['engine_version' => '1.0.0-demo', 'started_at' => now()->subHours(4)->setSecond(0)],
            [
                'uuid' => (string) Str::uuid(),
                'status' => 'completed',
                'triggered_by' => $admin->id,
                'completed_at' => now()->subHours(4)->addMinutes(2),
                'rules_executed' => 6,
                'indicators_created' => 5,
                'threshold_snapshot' => ['demo' => true, 'engine' => 'deterministic'],
                'summary_payload' => ['critical' => 1, 'warning' => 3, 'info' => 1],
                'notes' => 'Demo integrity run for CivicLens v1 inspection.',
            ],
        );

        $rules = IntelligenceRule::query()->get()->keyBy('slug');
        $signals = [
            ['project-delay-risk', $projects[4], 'projects', 'warning', 82, 'Demo: Schedule slippage requires review', 'Progress is below expected level for the planned completion window.'],
            ['budget-overrun-risk', $projects[0]->budgets()->first(), 'finance', 'critical', 91, 'Demo: Budget pressure above threshold', 'Actual and committed spending indicate a budget-pressure signal.'],
            ['procurement-single-bid-risk', $projects[1]->tenders()->first(), 'procurement', 'warning', 77, 'Demo: Low competition procurement signal', 'Tender participation is below the configured competition threshold.'],
            ['contractor-compliance-expiry', $organizations[3], 'contractors', 'warning', 73, 'Demo: Contractor compliance review needed', 'Contractor risk profile and recent award concentration require manual review.'],
            ['document-missing-metadata', Document::query()->where('title', 'like', 'Demo:%')->latest()->first(), 'documents', 'info', 61, 'Demo: Document metadata completeness gap', 'One or more public document fields remain incomplete for audit readiness.'],
        ];

        foreach ($signals as $signal) {
            [$ruleSlug, $source, $module, $severity, $confidence, $title, $description] = $signal;
            $rule = $rules[$ruleSlug] ?? $rules->first();

            $indicator = IntelligenceIndicator::query()->updateOrCreate(
                ['title' => $title],
                [
                    'intelligence_rule_id' => $rule->id,
                    'source_type' => $source::class,
                    'source_id' => $source->id,
                    'module' => $module,
                    'description' => $description,
                    'severity' => $severity,
                    'confidence_score' => $confidence,
                    'status' => 'pending',
                    'detected_at' => now()->subHours(3),
                    'rule_version' => $rule->version,
                    'detection_payload' => ['threshold' => $rule->thresholds, 'actual_value' => $confidence, 'run_id' => $run->id],
                    'metadata' => ['recommendation' => 'Human review required before any conclusion.', 'demo' => true],
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );

            IntelligenceEvidence::query()->updateOrCreate(
                ['intelligence_indicator_id' => $indicator->id, 'label' => 'Demo source evidence'],
                [
                    'evidenceable_type' => $source::class,
                    'evidenceable_id' => $source->id,
                    'summary' => 'Evidence is linked to the source record and can be reproduced from deterministic thresholds.',
                    'weight' => 80,
                    'payload' => ['source_id' => $source->id, 'source_type' => $source::class, 'rule_slug' => $ruleSlug],
                    'created_by' => $admin->id,
                ],
            );
        }
    }
}
