<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createLookupTable('organization_company_types');
        $this->createLookupTable('organization_industries');
        $this->createLookupTable('contractor_categories');
        $this->createLookupTable('contractor_classifications');
        $this->createLookupTable('contractor_registration_statuses');
        $this->createLookupTable('contractor_risk_levels');
        $this->createLookupTable('license_types');
        $this->createLookupTable('certification_types');
        $this->createLookupTable('compliance_types');
        $this->createLookupTable('compliance_statuses');
        $this->createLookupTable('contractor_activity_types');

        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_company_type_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('organization_industry_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('division_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('upazila_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('union_id')->nullable()->constrained('unions')->nullOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained()->nullOnDelete();
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->string('registration_number')->unique();
            $table->string('tax_identification_number')->nullable()->unique();
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('headquarters_address')->nullable();
            $table->string('status')->default('active')->index();
            $table->date('established_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_company_type_id', 'organization_industry_id'], 'organizations_type_industry_idx');
            $table->index(['country_id', 'district_id'], 'organizations_geography_idx');
            $table->index(['legal_name', 'registration_number'], 'organizations_search_idx');
        });

        Schema::create('branch_offices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('division_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('upazila_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('union_id')->nullable()->constrained('unions')->nullOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained()->nullOnDelete();
            $table->text('address');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['country_id', 'district_id'], 'branch_offices_geography_idx');
        });

        Schema::create('contractor_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('contractor_category_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('contractor_classification_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('contractor_registration_status_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('contractor_risk_level_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_suspended')->default(false)->index();
            $table->boolean('is_blacklisted')->default(false)->index();
            $table->boolean('is_public')->default(true)->index();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();

            $table->index(['contractor_category_id', 'contractor_risk_level_id'], 'contractor_profiles_category_risk_idx');
            $table->index(['contractor_registration_status_id', 'is_active'], 'contractor_profiles_status_active_idx');
        });

        Schema::create('directors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name');
            $table->string('position');
            $table->date('appointment_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'position']);
        });

        Schema::create('contact_people', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name');
            $table->string('position')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_primary')->default(false)->index();
            $table->timestamps();

            $table->index(['organization_id', 'is_primary']);
        });

        Schema::create('contractor_licenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_profile_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('license_type_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('license_number');
            $table->string('issuing_authority');
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->unique(['license_type_id', 'license_number']);
            $table->index(['contractor_profile_id', 'status']);
        });

        Schema::create('contractor_certifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_profile_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('certification_type_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('certificate_number')->nullable();
            $table->string('issuing_authority')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->index(['contractor_profile_id', 'certification_type_id'], 'contractor_certifications_profile_type_idx');
        });

        Schema::create('insurance_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_profile_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('provider');
            $table->string('policy_number');
            $table->decimal('coverage_amount', 16, 2)->default(0);
            $table->string('currency', 3)->default('BDT');
            $table->date('expiry_date')->nullable()->index();
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->unique(['provider', 'policy_number']);
            $table->index(['contractor_profile_id', 'status']);
        });

        Schema::create('compliance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_profile_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('compliance_type_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('compliance_status_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->text('findings')->nullable();
            $table->date('inspection_date')->nullable()->index();
            $table->date('next_review_date')->nullable()->index();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['contractor_profile_id', 'compliance_type_id'], 'compliance_records_profile_type_idx');
            $table->index(['compliance_status_id', 'inspection_date'], 'compliance_records_status_date_idx');
        });

        Schema::create('legal_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_profile_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('case_number');
            $table->string('court');
            $table->string('status')->index();
            $table->date('filing_date')->nullable()->index();
            $table->date('resolution_date')->nullable();
            $table->text('summary')->nullable();
            $table->boolean('is_confidential')->default(false)->index();
            $table->timestamps();

            $table->unique(['court', 'case_number']);
            $table->index(['contractor_profile_id', 'status']);
        });

        Schema::create('blacklist_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_profile_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->date('started_on')->index();
            $table->date('ended_on')->nullable()->index();
            $table->text('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['contractor_profile_id', 'started_on']);
        });

        Schema::create('contractor_performance_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_profile_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agency_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('budget_id')->nullable()->constrained()->nullOnDelete();
            $table->date('planned_completion_date')->nullable();
            $table->date('actual_completion_date')->nullable();
            $table->integer('delay_days')->default(0);
            $table->decimal('final_cost', 16, 2)->default(0);
            $table->decimal('cost_variance', 16, 2)->default(0);
            $table->unsignedTinyInteger('quality_rating')->default(0);
            $table->unsignedTinyInteger('agency_evaluation')->default(0);
            $table->string('completion_status')->index();
            $table->timestamps();

            $table->index(['contractor_profile_id', 'completion_status'], 'contractor_snapshots_profile_status_idx');
            $table->index(['project_id', 'agency_id'], 'contractor_snapshots_project_agency_idx');
        });

        Schema::create('contractor_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contractor_activity_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event')->index();
            $table->text('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['contractor_profile_id', 'event'], 'contractor_activities_profile_event_idx');
            $table->index(['organization_id', 'event'], 'contractor_activities_org_event_idx');
        });
    }

    public function down(): void
    {
        foreach ([
            'contractor_activities',
            'contractor_performance_snapshots',
            'blacklist_histories',
            'legal_cases',
            'compliance_records',
            'insurance_policies',
            'contractor_certifications',
            'contractor_licenses',
            'contact_people',
            'directors',
            'contractor_profiles',
            'branch_offices',
            'organizations',
            'contractor_activity_types',
            'compliance_statuses',
            'compliance_types',
            'certification_types',
            'license_types',
            'contractor_risk_levels',
            'contractor_registration_statuses',
            'contractor_classifications',
            'contractor_categories',
            'organization_industries',
            'organization_company_types',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function createLookupTable(string $table): void
    {
        Schema::create($table, function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }
};
