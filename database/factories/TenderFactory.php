<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\ProcurementMethod;
use App\Models\Tender;
use App\Models\TenderCategory;
use App\Models\TenderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tender>
 */
class TenderFactory extends Factory
{
    public function definition(): array
    {
        $budget = Budget::factory()->create();
        $title = fake()->unique()->sentence(4);

        return [
            'project_id' => $budget->project_id,
            'budget_id' => $budget->id,
            'agency_id' => $budget->project->agency_id,
            'procurement_method_id' => ProcurementMethod::factory(),
            'tender_category_id' => TenderCategory::factory(),
            'tender_status_id' => TenderStatus::factory(),
            'tender_number' => 'TDR-'.fake()->unique()->numberBetween(1000, 999999),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(100, 9999),
            'description' => fake()->paragraph(),
            'published_at' => null,
            'closing_at' => fake()->dateTimeBetween('+1 month', '+6 months'),
            'is_public' => true,
            'is_active' => true,
        ];
    }
}
