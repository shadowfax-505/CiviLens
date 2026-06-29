<?php

namespace Database\Factories;

use App\Models\BidDocument;
use App\Models\BidSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BidDocument>
 */
class BidDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'bid_submission_id' => BidSubmission::factory(),
            'uploaded_by' => User::factory(),
            'title' => fake()->sentence(3),
            'document_type' => 'technical',
            'file_path' => 'procurement/bids/'.fake()->uuid().'.pdf',
            'uploaded_at' => now(),
        ];
    }
}
