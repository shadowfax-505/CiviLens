<?php

namespace Database\Factories;

use App\Models\SourceEndpoint;
use App\Models\SourcePublisher;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SourceEndpoint> */
class SourceEndpointFactory extends Factory
{
    protected $model = SourceEndpoint::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'source_publisher_id' => SourcePublisher::factory(),
            'name' => fake()->words(3, true),
            'connector_type' => 'direct_download',
            'base_url' => 'https://data.example/publication.pdf',
            'allowed_hosts' => ['data.example'],
            'allowed_path_prefixes' => ['/'],
            'access_decision' => 'robots-and-terms-reviewed',
            'access_reviewed_at' => now(),
            'crawl_interval_minutes' => 1440,
            'rate_limit_per_minute' => 10,
            'timeout_seconds' => 20,
            'max_content_bytes' => 20971520,
            'health_status' => 'pending',
        ];
    }
}
