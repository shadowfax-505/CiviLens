<?php

namespace Database\Factories;

use App\Models\CitizenReport;
use App\Models\CitizenReportCategory;
use App\Models\CitizenReportStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CitizenReport>
 */
class CitizenReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'public_uuid' => (string) Str::uuid(),
            'citizen_report_category_id' => CitizenReportCategory::factory(),
            'citizen_report_status_id' => CitizenReportStatus::factory(),
            'submitter_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'location_text' => fake()->streetAddress(),
            'contact_preference' => 'email',
            'submitted_at' => now(),
        ];
    }
}
