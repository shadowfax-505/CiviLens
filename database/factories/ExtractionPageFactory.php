<?php

namespace Database\Factories;

use App\Models\ExtractionPage;
use App\Models\ExtractionRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExtractionPage> */
class ExtractionPageFactory extends Factory
{
    protected $model = ExtractionPage::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'extraction_run_id' => ExtractionRun::factory(),
            'page_number' => 1,
            'script_class' => 'mixed',
            'text_layer_density' => 0.42,
            'extraction_path' => 'native',
            'confidence' => 0.9,
            'character_count' => 128,
            'word_count' => 24,
            'duration_ms' => 15,
        ];
    }
}
