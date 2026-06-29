<?php

namespace Database\Factories;

use App\Models\CompletionCertificate;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompletionCertificate>
 */
class CompletionCertificateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'issued_by' => User::factory(),
            'certificate_number' => 'CERT-'.fake()->unique()->numberBetween(1000, 999999),
            'issued_at' => now()->toDateString(),
            'status' => 'draft',
            'notes' => fake()->sentence(),
        ];
    }
}
