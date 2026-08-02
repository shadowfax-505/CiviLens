<?php

namespace Database\Factories;

use App\Models\DiscoveredResource;
use App\Models\SourceArtifactVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SourceArtifactVersion> */
class SourceArtifactVersionFactory extends Factory
{
    protected $model = SourceArtifactVersion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $sha256 = hash('sha256', fake()->uuid());

        return [
            'discovered_resource_id' => DiscoveredResource::factory(),
            'version_number' => 1,
            'storage_disk' => 'local',
            'storage_path' => 'ingestion/quarantine/tests/'.$sha256.'.bin',
            'media_type' => 'application/octet-stream',
            'byte_size' => 128,
            'sha256' => $sha256,
            'retrieval_url' => 'https://data.example/file.bin',
            'malware_status' => 'unavailable',
            'is_quarantined' => true,
            'retrieved_at' => now(),
        ];
    }
}
