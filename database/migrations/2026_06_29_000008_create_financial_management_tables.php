<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'name']);
        });

        Schema::create('budget_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'name']);
        });

        Schema::create('budget_statuses', function (Blueprint $table): void {
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

        Schema::create('budget_transaction_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('direction')->default('neutral');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'direction']);
        });

        Schema::create('budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('budget_type_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('funding_source_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('budget_category_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('budget_status_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->decimal('original_allocation', 15, 2)->default(0);
            $table->decimal('current_allocation', 15, 2)->default(0);
            $table->decimal('reserved_amount', 15, 2)->default(0);
            $table->decimal('committed_amount', 15, 2)->default(0);
            $table->decimal('actual_expenditure', 15, 2)->default(0);
            $table->string('currency', 3)->default('BDT');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'fiscal_year_id']);
            $table->index(['budget_type_id', 'budget_status_id']);
            $table->index(['funding_source_id', 'budget_category_id']);
            $table->index(['current_allocation', 'actual_expenditure']);
            $table->index(['is_active', 'archived_at']);
        });

        Schema::create('budget_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->decimal('previous_allocation', 15, 2);
            $table->decimal('new_allocation', 15, 2);
            $table->decimal('difference', 15, 2);
            $table->text('reason');
            $table->date('approval_date')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['budget_id', 'revision_number']);
            $table->index(['approved_by', 'approval_date']);
        });

        Schema::create('budget_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('budget_transaction_type_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('transaction_date');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['budget_id', 'transaction_date']);
            $table->index(['budget_transaction_type_id', 'transaction_date'], 'budget_tx_type_date_idx');
            $table->index(['user_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_transactions');
        Schema::dropIfExists('budget_revisions');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('budget_transaction_types');
        Schema::dropIfExists('budget_statuses');
        Schema::dropIfExists('budget_types');
        Schema::dropIfExists('budget_categories');
    }
};
