<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intelligence_rule_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('intelligence_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('intelligence_rule_type_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('module')->index();
            $table->string('category')->index();
            $table->string('severity_default')->default('info')->index();
            $table->json('thresholds')->nullable();
            $table->json('configuration')->nullable();
            $table->string('version')->default('1.0.0');
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['module', 'is_active']);
            $table->index(['category', 'severity_default']);
        });

        Schema::create('intelligence_indicators', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('intelligence_rule_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->nullableMorphs('source');
            $table->string('module')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('severity')->default('info')->index();
            $table->unsignedTinyInteger('confidence_score')->default(0);
            $table->string('status')->default('pending')->index();
            $table->timestamp('detected_at')->index();
            $table->string('rule_version');
            $table->json('detection_payload')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['module', 'severity', 'status']);
            $table->index(['source_type', 'source_id', 'status']);
        });

        Schema::create('intelligence_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('intelligence_indicator_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->nullableMorphs('evidenceable');
            $table->string('label');
            $table->text('summary')->nullable();
            $table->unsignedTinyInteger('weight')->default(50);
            $table->json('payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['intelligence_indicator_id', 'weight']);
        });

        Schema::create('intelligence_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('intelligence_indicator_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('reviewed_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('status')->index();
            $table->text('notes')->nullable();
            $table->timestamp('reviewed_at')->index();
            $table->date('follow_up_on')->nullable()->index();
            $table->timestamps();

            $table->index(['intelligence_indicator_id', 'status']);
        });

        Schema::create('intelligence_processing_jobs', function (Blueprint $table): void {
            $table->id();
            $table->nullableMorphs('target');
            $table->string('job_type')->index();
            $table->string('status')->default('queued')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->json('payload')->nullable();
            $table->text('error_summary')->nullable();
            $table->foreignId('queued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('queued_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['target_type', 'target_id', 'job_type'], 'intel_jobs_target_type_id_job_idx');
            $table->index(['status', 'queued_at']);
        });

        Schema::create('intelligence_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('intelligence_indicator_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('intelligence_rule_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('intelligence_processing_job_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event')->index();
            $table->text('description')->nullable();
            $table->json('properties')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['event', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_activities');
        Schema::dropIfExists('intelligence_processing_jobs');
        Schema::dropIfExists('intelligence_reviews');
        Schema::dropIfExists('intelligence_evidence');
        Schema::dropIfExists('intelligence_indicators');
        Schema::dropIfExists('intelligence_rules');
        Schema::dropIfExists('intelligence_rule_types');
    }
};
