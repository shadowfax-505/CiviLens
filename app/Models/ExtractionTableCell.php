<?php

namespace App\Models;

use Database\Factories\ExtractionTableCellFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtractionTableCell extends Model
{
    /** @use HasFactory<ExtractionTableCellFactory> */
    use HasFactory;

    protected $fillable = [
        'extraction_page_id',
        'table_index',
        'row_index',
        'column_index',
        'row_span',
        'column_span',
        'box_left',
        'box_top',
        'box_right',
        'box_bottom',
        'text',
        'word_count',
    ];

    protected function casts(): array
    {
        return [
            'table_index' => 'integer',
            'row_index' => 'integer',
            'column_index' => 'integer',
            'row_span' => 'integer',
            'column_span' => 'integer',
            'box_left' => 'integer',
            'box_top' => 'integer',
            'box_right' => 'integer',
            'box_bottom' => 'integer',
            'word_count' => 'integer',
        ];
    }

    /** @return BelongsTo<ExtractionPage, self> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(ExtractionPage::class, 'extraction_page_id');
    }

    /**
     * Whether a point sits inside this cell.
     *
     * A word is placed by its centre rather than its corner, so a character that
     * overhangs a ruling line does not move the whole word into the next column.
     */
    public function contains(float $x, float $y): bool
    {
        return $x >= $this->box_left && $x <= $this->box_right
            && $y >= $this->box_top && $y <= $this->box_bottom;
    }
}
