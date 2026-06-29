<?php

namespace Database\Factories;

use App\Models\Award;
use App\Models\BidSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Award>
 */
class AwardFactory extends Factory
{
    public function definition(): array
    {
        $bid = BidSubmission::factory()->create();

        return [
            'tender_id' => $bid->tender_id,
            'bid_submission_id' => $bid->id,
            'awarded_at' => now()->toDateString(),
            'status' => 'pending',
            'notes' => fake()->sentence(),
        ];
    }
}
