<?php

namespace Database\Factories;

use App\Models\ExtractionField;
use App\Models\ExtractionRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExtractionField> */
class ExtractionFieldFactory extends Factory
{
    protected $model = ExtractionField::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'extraction_run_id' => ExtractionRun::factory(),
            'field_key' => 'tender_reference',
            'field_type' => 'identifier',
            'extracted_value' => 'TND-2026-000123',
            'script_class' => 'mixed',
            'publisher_group' => 'demo-publisher',
            'calibration_split' => 'calibration',
            'confidence' => 0.87,
            'nonconformity_score' => 0.13,
            'decision' => 'pending',
        ];
    }
}
