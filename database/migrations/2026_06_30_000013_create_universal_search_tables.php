<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_indexes', function (Blueprint $table): void {
            $table->id();
            $table->string('searchable_type');
            $table->unsignedBigInteger('searchable_id');
            $table->string('module')->index();
            $table->string('title')->index();
            $table->text('description')->nullable();
            $table->string('url')->nullable();
            $table->string('visibility')->default('internal')->index();
            $table->string('status')->nullable()->index();
            $table->longText('search_text')->nullable();
            $table->string('embedding_reference')->nullable();
            $table->string('semantic_hash')->nullable()->index();
            $table->text('entity_summary')->nullable();
            $table->longText('search_vector')->nullable();
            $table->json('entity_keywords')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_embedding_update')->nullable();
            $table->timestamp('indexed_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['searchable_type', 'searchable_id']);
            $table->index(['module', 'visibility', 'status']);
            $table->index(['searchable_type', 'searchable_id']);
        });

        Schema::create('search_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('search_index_id')->constrained('search_indexes')->cascadeOnDelete();
            $table->string('locale', 16)->default('en');
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->string('body_hash')->nullable()->index();
            $table->json('indexed_payload')->nullable();
            $table->timestamps();

            $table->unique(['search_index_id', 'locale']);
        });

        Schema::create('search_keywords', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('search_index_id')->constrained('search_indexes')->cascadeOnDelete();
            $table->string('keyword')->index();
            $table->unsignedSmallInteger('weight')->default(1);
            $table->timestamps();

            $table->unique(['search_index_id', 'keyword']);
        });

        Schema::create('search_synonyms', function (Blueprint $table): void {
            $table->id();
            $table->string('term')->index();
            $table->string('synonym')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['term', 'synonym']);
        });

        Schema::create('search_popularity', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('search_index_id')->unique()->constrained('search_indexes')->cascadeOnDelete();
            $table->unsignedBigInteger('searches_count')->default(0);
            $table->unsignedBigInteger('clicks_count')->default(0);
            $table->decimal('popularity_score', 10, 2)->default(0);
            $table->timestamp('last_clicked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('search_clicks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('search_index_id')->nullable()->constrained('search_indexes')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('query')->nullable()->index();
            $table->unsignedInteger('result_position')->default(0);
            $table->timestamp('clicked_at')->index();
            $table->timestamps();
        });

        Schema::create('saved_searches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('query')->nullable();
            $table->string('module')->nullable()->index();
            $table->json('filters')->nullable();
            $table->string('sort')->default('relevance');
            $table->string('direction', 8)->default('desc');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'module']);
        });

        Schema::create('search_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('query')->nullable()->index();
            $table->string('module')->nullable()->index();
            $table->json('filters')->nullable();
            $table->unsignedInteger('results_count')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->boolean('successful')->default(true)->index();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('search_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('search_index_id')->nullable()->constrained('search_indexes')->nullOnDelete();
            $table->string('searchable_type')->nullable();
            $table->unsignedBigInteger('searchable_id')->nullable();
            $table->string('operation')->index();
            $table->string('status')->default('queued')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['searchable_type', 'searchable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_jobs');
        Schema::dropIfExists('search_history');
        Schema::dropIfExists('saved_searches');
        Schema::dropIfExists('search_clicks');
        Schema::dropIfExists('search_popularity');
        Schema::dropIfExists('search_synonyms');
        Schema::dropIfExists('search_keywords');
        Schema::dropIfExists('search_documents');
        Schema::dropIfExists('search_indexes');
    }
};
