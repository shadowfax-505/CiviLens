<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentActivityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'actor_id' => User::factory(),
            'event' => 'document.uploaded',
            'description' => fake()->sentence(),
            'old_values' => null,
            'new_values' => ['title' => fake()->sentence(3)],
        ];
    }
}
