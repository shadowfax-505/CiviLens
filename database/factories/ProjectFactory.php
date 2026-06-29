<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\Country;
use App\Models\FiscalYear;
use App\Models\FundingSource;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectPriority;
use App\Models\ProjectStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->sentence(3);

        return [
            'project_code' => 'PRJ-'.fake()->unique()->numberBetween(1000, 999999),
            'name' => $name,
            'short_name' => strtoupper(fake()->unique()->lexify('???')),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 9999),
            'description' => fake()->paragraph(),
            'agency_id' => Agency::factory(),
            'project_category_id' => ProjectCategory::factory(),
            'project_status_id' => ProjectStatus::factory(),
            'project_priority_id' => ProjectPriority::factory(),
            'funding_source_id' => FundingSource::factory(),
            'fiscal_year_id' => FiscalYear::factory(),
            'country_id' => Country::factory(),
            'progress_percentage' => fake()->numberBetween(0, 100),
            'planned_start_date' => fake()->dateTimeBetween('-1 year', '+1 year')->format('Y-m-d'),
            'planned_end_date' => fake()->dateTimeBetween('+1 year', '+3 years')->format('Y-m-d'),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'geojson' => null,
            'featured_image_path' => null,
            'is_public' => fake()->boolean(70),
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
