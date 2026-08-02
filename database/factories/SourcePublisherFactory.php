<?php

namespace Database\Factories;

use App\Models\SourcePublisher;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SourcePublisher> */
class SourcePublisherFactory extends Factory
{
    protected $model = SourcePublisher::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->company();
        $host = Str::slug($name).'.example';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'source_class' => 'government',
            'canonical_url' => 'https://'.$host,
            'attribution_name' => $name,
            'rights_decision' => 'reviewed-public-interest',
            'is_active' => true,
        ];
    }
}
