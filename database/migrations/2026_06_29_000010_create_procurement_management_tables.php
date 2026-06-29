<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'name']);
        });

        Schema::create('tender_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'name']);
        });

        Schema::create('tender_statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('bidder_organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('registration_number')->nullable()->index();
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->text('address')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'name']);
        });

        Schema::create('tenders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('budget_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('agency_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('procurement_method_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('tender_category_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('tender_status_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('tender_number')->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closing_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'budget_id']);
            $table->index(['agency_id', 'tender_status_id']);
            $table->index(['procurement_method_id', 'tender_category_id']);
            $table->index(['published_at', 'closing_at']);
            $table->index(['is_active', 'archived_at']);
        });

        Schema::create('tender_lots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tender_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('lot_number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tender_id', 'lot_number']);
        });

        Schema::create('bid_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tender_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('bidder_organization_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('reference_number')->unique();
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('technical_score', 8, 2)->nullable();
            $table->decimal('financial_score', 8, 2)->nullable();
            $table->string('status')->default('submitted');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tender_id', 'status']);
            $table->index(['bidder_organization_id', 'submitted_at']);
        });

        Schema::create('bid_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bid_submission_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('document_type')->nullable();
            $table->string('file_path');
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->index(['bid_submission_id', 'document_type']);
        });

        Schema::create('evaluation_committees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tender_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name');
            $table->date('formed_at')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['tender_id', 'status']);
        });

        Schema::create('committee_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evaluation_committee_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('role')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();

            $table->index(['evaluation_committee_id', 'role']);
        });

        Schema::create('evaluation_criteria', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tender_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('max_score', 8, 2)->default(100);
            $table->decimal('weight', 5, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tender_id', 'sort_order']);
        });

        Schema::create('evaluation_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bid_submission_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('evaluation_criterion_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('committee_member_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('score', 8, 2);
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->unique(['bid_submission_id', 'evaluation_criterion_id', 'committee_member_id'], 'evaluation_scores_unique_reviewer_score');
        });

        Schema::create('awards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tender_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('bid_submission_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->date('awarded_at')->nullable();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tender_id', 'status']);
            $table->index(['bid_submission_id', 'awarded_at']);
        });

        Schema::create('contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('award_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('bid_submission_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('budget_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('contract_number')->unique();
            $table->string('title');
            $table->string('status')->default('draft');
            $table->date('signed_at')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'budget_id']);
            $table->index(['award_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });

        Schema::create('contract_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('title');
            $table->date('due_date')->nullable();
            $table->date('completed_at')->nullable();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['contract_id', 'status']);
        });

        Schema::create('variation_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('approved_at')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['contract_id', 'status']);
        });

        Schema::create('contract_extensions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->date('previous_end_date');
            $table->date('new_end_date');
            $table->text('reason');
            $table->date('approved_at')->nullable();
            $table->timestamps();

            $table->index(['contract_id', 'new_end_date']);
        });

        Schema::create('liquidated_damages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->text('reason');
            $table->date('assessed_at')->nullable();
            $table->unsignedInteger('days_delayed')->nullable();
            $table->decimal('assessed_amount', 15, 2)->nullable();
            $table->timestamps();

            $table->index(['contract_id', 'assessed_at']);
        });

        Schema::create('completion_certificates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('certificate_number')->nullable()->unique();
            $table->date('issued_at')->nullable();
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['contract_id', 'status']);
        });

        Schema::create('procurement_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tender_id')->nullable()->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event')->index();
            $table->text('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamps();

            $table->index(['tender_id', 'created_at']);
            $table->index(['contract_id', 'created_at']);
            $table->index(['actor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_activities');
        Schema::dropIfExists('completion_certificates');
        Schema::dropIfExists('liquidated_damages');
        Schema::dropIfExists('contract_extensions');
        Schema::dropIfExists('variation_orders');
        Schema::dropIfExists('contract_milestones');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('awards');
        Schema::dropIfExists('evaluation_scores');
        Schema::dropIfExists('evaluation_criteria');
        Schema::dropIfExists('committee_members');
        Schema::dropIfExists('evaluation_committees');
        Schema::dropIfExists('bid_documents');
        Schema::dropIfExists('bid_submissions');
        Schema::dropIfExists('tender_lots');
        Schema::dropIfExists('tenders');
        Schema::dropIfExists('bidder_organizations');
        Schema::dropIfExists('tender_statuses');
        Schema::dropIfExists('tender_categories');
        Schema::dropIfExists('procurement_methods');
    }
};
