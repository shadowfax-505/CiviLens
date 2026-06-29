<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DocumentVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'version_number' => 1,
            'original_filename' => 'document.pdf',
            'stored_filename' => Str::uuid().'.pdf',
            'storage_disk' => 'local',
            'storage_path' => 'documents/'.Str::uuid().'.pdf',
            'file_extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'checksum' => hash('sha256', fake()->uuid()),
            'file_size' => fake()->numberBetween(1024, 40960),
            'uploaded_by' => User::factory(),
            'reason' => 'Initial upload.',
            'is_current' => true,
        ];
    }
}
