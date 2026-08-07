<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extraction_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('source_artifact_version_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('running');
            $table->string('routing_decision', 32)->default('pending');
            $table->string('engine', 64);
            $table->string('engine_version', 64);
            $table->char('config_hash', 64);
            $table->string('language_hint', 32)->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->unsignedInteger('pages_native')->default(0);
            $table->unsignedInteger('pages_ocr_primary')->default(0);
            $table->unsignedInteger('pages_ocr_enhanced')->default(0);
            $table->unsignedInteger('pages_abstained')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedBigInteger('peak_memory_bytes')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('review_status', 32)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['source_artifact_version_id', 'status']);
            $table->index(['review_status', 'completed_at']);
            $table->index(['engine', 'engine_version']);
        });

        Schema::create('extraction_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('extraction_run_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedInteger('page_number');
            $table->string('script_class', 16)->default('unknown');
            $table->decimal('text_layer_density', 8, 6)->nullable();
            $table->string('extraction_path', 32);
            $table->decimal('confidence', 5, 4)->nullable();
            $table->longText('extracted_text')->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->unsignedInteger('character_count')->default(0);
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->unique(['extraction_run_id', 'page_number'], 'extraction_page_run_number_unique');
            $table->index(['script_class', 'extraction_path']);
            $table->index(['extraction_run_id', 'confidence']);
        });

        Schema::create('extraction_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('extraction_run_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('extraction_page_id')->nullable()->constrained()->nullOnDelete();
            $table->string('field_key', 96);
            $table->string('field_type', 32)->default('string');
            $table->text('extracted_value')->nullable();
            $table->text('normalized_value')->nullable();
            $table->string('script_class', 16)->default('unknown');
            $table->string('publisher_group', 96);
            $table->string('calibration_split', 16)->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->decimal('nonconformity_score', 12, 8)->nullable();
            $table->unsignedInteger('prediction_set_size')->nullable();
            $table->string('decision', 16)->default('pending');
            $table->decimal('decision_alpha', 5, 4)->nullable();
            $table->unsignedInteger('evidence_page_number')->nullable();
            $table->unsignedInteger('evidence_offset_start')->nullable();
            $table->unsignedInteger('evidence_offset_end')->nullable();
            $table->text('gold_value')->nullable();
            $table->string('gold_source', 32)->nullable();
            $table->boolean('is_correct')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['publisher_group', 'script_class', 'is_correct'], 'extraction_field_group_outcome_idx');
            $table->index(['calibration_split', 'publisher_group'], 'extraction_field_split_group_idx');
            $table->index(['decision', 'reviewed_at']);
            $table->index(['extraction_run_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extraction_fields');
        Schema::dropIfExists('extraction_pages');
        Schema::dropIfExists('extraction_runs');
    }
};
