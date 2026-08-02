<?php

namespace Database\Factories;

use App\Models\DiscoveredResource;
use App\Models\SourceEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DiscoveredResource> */
class DiscoveredResourceFactory extends Factory
{
    protected $model = DiscoveredResource::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $url = 'https://data.example/'.fake()->unique()->slug().'.pdf';

        return [
            'source_endpoint_id' => SourceEndpoint::factory(),
            'canonical_url' => $url,
            'canonical_url_hash' => hash('sha256', $url),
            'resource_type' => 'document',
            'status' => 'discovered',
            'last_seen_at' => now(),
        ];
    }
}
