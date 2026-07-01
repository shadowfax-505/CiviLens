<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bidder_organizations', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index(['organization_id', 'registration_number'], 'bidder_org_contractor_registration_idx');
        });

        Schema::create('procurement_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('plan_number')->unique();
            $table->string('title');
            $table->foreignId('agency_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('budget_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('funding_source_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('procurement_method_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->decimal('estimated_value', 15, 2);
            $table->string('priority')->default('normal')->index();
            $table->date('planned_start_date')->nullable();
            $table->date('planned_award_date')->nullable();
            $table->date('planned_completion_date')->nullable();
            $table->string('status')->default('draft')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->index();
            $table->text('approval_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['agency_id', 'status']);
            $table->index(['project_id', 'fiscal_year_id']);
            $table->index(['procurement_method_id', 'priority']);
        });

        Schema::create('procurement_plan_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('procurement_plan_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event')->index();
            $table->text('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamps();

            $table->index(['procurement_plan_id', 'created_at']);
        });

        Schema::table('bid_submissions', function (Blueprint $table): void {
            $table->decimal('bid_amount', 15, 2)->nullable()->after('submitted_at');
            $table->date('bid_valid_until')->nullable()->after('bid_amount');
            $table->decimal('bid_security_amount', 15, 2)->nullable()->after('bid_valid_until');
            $table->text('technical_proposal_summary')->nullable()->after('bid_security_amount');
            $table->text('financial_proposal_summary')->nullable()->after('technical_proposal_summary');
            $table->timestamp('opened_at')->nullable()->index()->after('financial_proposal_summary');
            $table->timestamp('withdrawn_at')->nullable()->index()->after('opened_at');
        });

        Schema::create('bid_opening_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bid_submission_id')->unique()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at')->index();
            $table->decimal('recorded_amount', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('bid_withdrawals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bid_submission_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('withdrawn_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('withdrawn_at')->index();
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('bid_compliance_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bid_submission_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('requirement');
            $table->string('status')->default('pending')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('evaluation_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bid_submission_id')->unique()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->decimal('technical_score', 8, 2)->default(0);
            $table->decimal('financial_score', 8, 2)->default(0);
            $table->decimal('compliance_score', 8, 2)->default(0);
            $table->decimal('overall_score', 8, 2)->default(0);
            $table->text('recommendation')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->index();
            $table->json('score_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('award_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('award_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('approved')->index();
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->index();
            $table->timestamps();
        });

        Schema::table('awards', function (Blueprint $table): void {
            $table->string('public_disclosure_status')->default('internal')->index()->after('status');
        });

        Schema::table('contract_milestones', function (Blueprint $table): void {
            $table->unsignedTinyInteger('completion_percentage')->default(0)->after('status');
            $table->foreignId('accepted_by')->nullable()->after('completion_percentage')->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable()->index()->after('accepted_by');
            $table->text('evidence_summary')->nullable()->after('accepted_at');
        });

        Schema::table('variation_orders', function (Blueprint $table): void {
            $table->decimal('approved_amount', 15, 2)->nullable()->after('approved_at');
            $table->unsignedInteger('schedule_extension_days')->nullable()->after('approved_amount');
            $table->text('reason')->nullable()->after('schedule_extension_days');
        });

        Schema::create('contract_deliverables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('contract_milestone_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('accepted_at')->nullable()->index();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->text('evidence_summary')->nullable();
            $table->timestamps();
        });

        Schema::create('contract_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('payment_reference')->unique();
            $table->decimal('amount', 15, 2);
            $table->date('paid_at')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('contract_closeouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->unique()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_closeouts');
        Schema::dropIfExists('contract_payments');
        Schema::dropIfExists('contract_deliverables');

        Schema::table('variation_orders', function (Blueprint $table): void {
            $table->dropColumn(['approved_amount', 'schedule_extension_days', 'reason']);
        });

        Schema::table('contract_milestones', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('accepted_by');
            $table->dropColumn(['completion_percentage', 'accepted_at', 'evidence_summary']);
        });

        Schema::table('awards', function (Blueprint $table): void {
            $table->dropColumn('public_disclosure_status');
        });

        Schema::dropIfExists('award_approvals');
        Schema::dropIfExists('evaluation_summaries');
        Schema::dropIfExists('bid_compliance_items');
        Schema::dropIfExists('bid_withdrawals');
        Schema::dropIfExists('bid_opening_records');

        Schema::table('bid_submissions', function (Blueprint $table): void {
            $table->dropColumn([
                'bid_amount',
                'bid_valid_until',
                'bid_security_amount',
                'technical_proposal_summary',
                'financial_proposal_summary',
                'opened_at',
                'withdrawn_at',
            ]);
        });

        Schema::dropIfExists('procurement_plan_activities');
        Schema::dropIfExists('procurement_plans');

        Schema::table('bidder_organizations', function (Blueprint $table): void {
            $table->dropIndex('bidder_org_contractor_registration_idx');
            $table->dropConstrainedForeignId('organization_id');
        });
    }
};
