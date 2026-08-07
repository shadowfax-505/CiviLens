<?php

namespace Database\Factories;

use App\Models\ExtractionRun;
use App\Models\SourceArtifactVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExtractionRun> */
class ExtractionRunFactory extends Factory
{
    protected $model = ExtractionRun::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'source_artifact_version_id' => SourceArtifactVersion::factory(),
            'status' => 'running',
            'routing_decision' => 'pending',
            'engine' => 'native',
            'engine_version' => '0.0.0-test',
            'config_hash' => hash('sha256', 'test-extraction-config'),
            'language_hint' => 'ben+eng',
            'started_at' => now(),
        ];
    }
}
