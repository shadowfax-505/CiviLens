<?php

namespace Database\Factories;

use App\Models\ExtractionPage;
use App\Models\ExtractionTableCell;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExtractionTableCell> */
class ExtractionTableCellFactory extends Factory
{
    protected $model = ExtractionTableCell::class;

    public function definition(): array
    {
        return [
            'extraction_page_id' => ExtractionPage::factory(),
            'table_index' => 0,
            'row_index' => 0,
            'column_index' => 0,
            'row_span' => 1,
            'column_span' => 1,
            'box_left' => 100,
            'box_top' => 100,
            'box_right' => 300,
            'box_bottom' => 140,
            'text' => 'cell',
            'word_count' => 1,
        ];
    }
}
