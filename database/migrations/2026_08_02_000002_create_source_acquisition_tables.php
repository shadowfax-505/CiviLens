<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_publishers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('source_class', 32);
            $table->string('canonical_url', 2048);
            $table->string('attribution_name');
            $table->string('rights_decision', 64)->default('reviewed-public-interest');
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['source_class', 'is_active']);
        });

        Schema::create('source_endpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_publisher_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name');
            $table->string('connector_type', 32);
            $table->string('base_url', 2048);
            $table->json('allowed_hosts');
            $table->json('allowed_path_prefixes');
            $table->string('access_decision', 64);
            $table->timestamp('access_reviewed_at');
            $table->unsignedInteger('crawl_interval_minutes')->default(1440);
            $table->unsignedInteger('rate_limit_per_minute')->default(10);
            $table->unsignedInteger('timeout_seconds')->default(20);
            $table->unsignedBigInteger('max_content_bytes')->default(20971520);
            $table->json('cursor')->nullable();
            $table->string('health_status', 32)->default('pending');
            $table->text('last_error')->nullable();
            $table->timestamp('last_crawled_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['paused_at', 'last_crawled_at']);
            $table->index(['health_status', 'connector_type']);
        });

        Schema::create('source_crawl_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('source_endpoint_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('running');
            $table->json('cursor_before')->nullable();
            $table->json('cursor_after')->nullable();
            $table->unsignedInteger('discovered_count')->default(0);
            $table->unsignedInteger('fetched_count')->default(0);
            $table->unsignedInteger('quarantined_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->text('error_summary')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['source_endpoint_id', 'started_at']);
            $table->index(['status', 'started_at']);
        });

        Schema::create('discovered_resources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_endpoint_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('source_crawl_run_id')->nullable()->constrained()->nullOnDelete();
            $table->string('canonical_url', 2048);
            $table->char('canonical_url_hash', 64);
            $table->string('discovery_url', 2048)->nullable();
            $table->string('external_id')->nullable();
            $table->string('resource_type', 32)->default('document');
            $table->string('status', 32)->default('discovered');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('last_seen_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source_endpoint_id', 'canonical_url_hash'], 'discovered_resource_endpoint_url_unique');
            $table->index(['status', 'last_seen_at']);
            $table->index(['source_endpoint_id', 'published_at']);
        });

        Schema::create('source_artifact_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('discovered_resource_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('source_crawl_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('document_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supersedes_id')->nullable()->constrained('source_artifact_versions')->nullOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('storage_disk', 64);
            $table->string('storage_path', 2048);
            $table->string('original_filename')->nullable();
            $table->string('media_type', 191);
            $table->unsignedBigInteger('byte_size');
            $table->char('sha256', 64);
            $table->string('retrieval_url', 2048);
            $table->string('http_etag')->nullable();
            $table->string('http_last_modified')->nullable();
            $table->json('response_headers')->nullable();
            $table->string('malware_status', 32);
            $table->boolean('is_quarantined')->default(true);
            $table->text('quarantine_reason')->nullable();
            $table->timestamp('retrieved_at');
            $table->timestamps();

            $table->unique(['discovered_resource_id', 'version_number'], 'artifact_resource_version_unique');
            $table->unique(['discovered_resource_id', 'sha256'], 'artifact_resource_checksum_unique');
            $table->index(['is_quarantined', 'malware_status']);
            $table->index(['sha256', 'retrieved_at']);
        });

        Schema::create('source_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_publisher_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_endpoint_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_crawl_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_artifact_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 96);
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['event', 'occurred_at']);
            $table->index(['source_endpoint_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_activities');
        Schema::dropIfExists('source_artifact_versions');
        Schema::dropIfExists('discovered_resources');
        Schema::dropIfExists('source_crawl_runs');
        Schema::dropIfExists('source_endpoints');
        Schema::dropIfExists('source_publishers');
    }
};
