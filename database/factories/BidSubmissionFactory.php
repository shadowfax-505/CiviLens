<?php

namespace Database\Factories;

use App\Models\BidderOrganization;
use App\Models\BidSubmission;
use App\Models\Tender;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BidSubmission>
 */
class BidSubmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tender_id' => Tender::factory(),
            'bidder_organization_id' => BidderOrganization::factory(),
            'reference_number' => 'BID-'.fake()->unique()->numberBetween(1000, 999999),
            'submitted_at' => now(),
            'technical_score' => fake()->randomFloat(2, 50, 100),
            'financial_score' => fake()->randomFloat(2, 50, 100),
            'status' => 'submitted',
            'notes' => fake()->sentence(),
        ];
    }
}
