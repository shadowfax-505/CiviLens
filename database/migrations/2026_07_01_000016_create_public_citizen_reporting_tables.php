<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citizen_report_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('citizen_report_statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_terminal')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('citizen_reports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('citizen_report_category_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('citizen_report_status_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('submitter_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('agency_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('division_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('upazila_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('union_id')->nullable()->constrained('unions')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('location_text')->nullable();
            $table->string('contact_preference')->default('email');
            $table->text('moderation_notes')->nullable();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['citizen_report_status_id', 'submitted_at']);
            $table->index(['submitter_id', 'submitted_at']);
            $table->index(['project_id', 'citizen_report_status_id']);
            $table->index(['agency_id', 'citizen_report_status_id']);
        });

        Schema::create('citizen_report_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('citizen_report_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->string('event')->index();
            $table->text('notes')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['citizen_report_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citizen_report_activities');
        Schema::dropIfExists('citizen_reports');
        Schema::dropIfExists('citizen_report_statuses');
        Schema::dropIfExists('citizen_report_categories');
    }
};
