<?php

namespace Database\Factories;

use App\Models\SourceCrawlRun;
use App\Models\SourceEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SourceCrawlRun> */
class SourceCrawlRunFactory extends Factory
{
    protected $model = SourceCrawlRun::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'source_endpoint_id' => SourceEndpoint::factory(),
            'status' => 'running',
            'started_at' => now(),
        ];
    }
}
