<?php

namespace Database\Factories;

use App\Models\DocumentCategory;
use App\Models\DocumentStatus;
use App\Models\DocumentType;
use App\Models\DocumentVisibility;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DocumentFactory extends Factory
{
    public function definition(): array
    {
        $filename = 'document-'.fake()->unique()->numberBetween(1000, 9999).'.pdf';

        return [
            'uuid' => (string) Str::uuid(),
            'document_type_id' => DocumentType::factory(),
            'document_category_id' => DocumentCategory::factory(),
            'document_status_id' => DocumentStatus::factory(),
            'document_visibility_id' => DocumentVisibility::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'original_filename' => $filename,
            'stored_filename' => Str::uuid().'.pdf',
            'storage_disk' => 'local',
            'storage_path' => 'documents/'.Str::uuid().'.pdf',
            'file_extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'checksum' => hash('sha256', fake()->uuid()),
            'file_size' => fake()->numberBetween(1024, 40960),
            'page_count' => fake()->numberBetween(1, 20),
            'language' => 'en',
            'owner_id' => User::factory(),
            'uploaded_by' => User::factory(),
            'version_number' => 1,
            'ocr_status' => 'pending',
            'index_status' => 'pending',
            'preview_status' => 'pending',
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
