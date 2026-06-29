<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createLookupTable('document_types');
        $this->createLookupTable('document_categories');
        $this->createLookupTable('document_statuses');
        $this->createLookupTable('document_visibilities');
        $this->createLookupTable('document_permission_types');

        Schema::create('document_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('document_type_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('document_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('document_status_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('document_visibility_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('storage_disk');
            $table->string('storage_path');
            $table->string('file_extension', 32)->index();
            $table->string('mime_type', 160)->index();
            $table->string('checksum', 128)->index();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->unsignedInteger('page_count')->nullable();
            $table->string('language', 16)->nullable()->index();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version_number')->default(1);
            $table->string('ocr_status')->default('pending')->index();
            $table->string('index_status')->default('pending')->index();
            $table->string('preview_status')->default('pending')->index();
            $table->string('thumbnail_path')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['document_type_id', 'document_status_id'], 'documents_type_status_idx');
            $table->index(['document_category_id', 'document_visibility_id'], 'documents_category_visibility_idx');
            $table->index(['owner_id', 'uploaded_by'], 'documents_owner_uploader_idx');
            $table->index(['created_at', 'file_size'], 'documents_created_size_idx');
        });

        Schema::create('document_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('storage_disk');
            $table->string('storage_path');
            $table->string('file_extension', 32);
            $table->string('mime_type', 160);
            $table->string('checksum', 128)->index();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->boolean('is_current')->default(false)->index();
            $table->timestamps();

            $table->unique(['document_id', 'version_number']);
            $table->index(['document_id', 'is_current']);
        });

        Schema::create('documentables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->morphs('documentable');
            $table->string('relationship_type')->default('supporting')->index();
            $table->timestamps();

            $table->unique(['document_id', 'documentable_type', 'documentable_id'], 'documentables_unique_link');
        });

        Schema::create('document_tag', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('document_tag_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['document_id', 'document_tag_id']);
        });

        Schema::create('document_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('document_permission_type_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('role_id')->nullable()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['document_id', 'role_id']);
            $table->index(['document_id', 'user_id']);
        });

        Schema::create('document_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('document_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event')->index();
            $table->text('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'event']);
            $table->index(['actor_id', 'event']);
        });

        Schema::create('document_ocr_metadata', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->unique()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->longText('extracted_text')->nullable();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->string('language', 16)->nullable();
            $table->string('ocr_engine')->nullable();
            $table->unsignedInteger('processing_time_ms')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('document_ai_metadata', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->unique()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('embedding_id')->nullable();
            $table->string('vector_reference')->nullable();
            $table->longText('summary')->nullable();
            $table->json('keywords')->nullable();
            $table->json('entities')->nullable();
            $table->json('topics')->nullable();
            $table->timestamp('last_ai_review_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'document_ai_metadata',
            'document_ocr_metadata',
            'document_activities',
            'document_permissions',
            'document_tag',
            'documentables',
            'document_versions',
            'documents',
            'document_tags',
            'document_permission_types',
            'document_visibilities',
            'document_statuses',
            'document_categories',
            'document_types',
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
