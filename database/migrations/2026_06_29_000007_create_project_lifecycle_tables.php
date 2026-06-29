<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::create('project_statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_terminal')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sort_order', 'name']);
        });

        Schema::create('project_priorities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sort_order', 'name']);
        });

        Schema::create('funding_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::create('fiscal_years', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'starts_on']);
        });

        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('project_code')->unique();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('agency_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('projects')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('project_category_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('project_status_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('project_priority_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('funding_source_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('division_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('upazila_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('union_id')->nullable()->constrained('unions')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->decimal('estimated_budget', 15, 2)->default(0);
            $table->decimal('approved_budget', 15, 2)->default(0);
            $table->decimal('spent_amount', 15, 2)->default(0);
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->date('planned_start_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('geojson')->nullable();
            $table->string('featured_image_path')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['agency_id', 'project_status_id']);
            $table->index(['project_category_id', 'project_priority_id']);
            $table->index(['funding_source_id', 'fiscal_year_id']);
            $table->index(['country_id', 'division_id', 'district_id']);
            $table->index(['upazila_id', 'union_id', 'ward_id']);
            $table->index(['is_public', 'is_active', 'archived_at']);
            $table->index(['planned_start_date', 'planned_end_date']);
            $table->index(['approved_budget', 'progress_percentage']);
        });

        Schema::create('project_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event')->index();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'event']);
            $table->index(['actor_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_activities');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('fiscal_years');
        Schema::dropIfExists('funding_sources');
        Schema::dropIfExists('project_priorities');
        Schema::dropIfExists('project_statuses');
        Schema::dropIfExists('project_categories');
    }
};
