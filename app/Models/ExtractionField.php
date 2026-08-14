<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtractionField extends Model
{
    use HasFactory;

    protected $fillable = [
        'extraction_run_id',
        'extraction_page_id',
        'extraction_table_cell_id',
        'field_key',
        'field_type',
        'extracted_value',
        'normalized_value',
        'script_class',
        'publisher_group',
        'calibration_split',
        'confidence',
        'nonconformity_score',
        'prediction_set_size',
        'decision',
        'decision_alpha',
        'evidence_page_number',
        'evidence_offset_start',
        'evidence_offset_end',
        'gold_value',
        'gold_source',
        'is_correct',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'nonconformity_score' => 'float',
            'prediction_set_size' => 'integer',
            'decision_alpha' => 'float',
            'evidence_page_number' => 'integer',
            'evidence_offset_start' => 'integer',
            'evidence_offset_end' => 'integer',
            'is_correct' => 'boolean',
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    /**
     * Rows eligible for conformal calibration: a known outcome and a score to rank it by.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCalibratable(Builder $query): Builder
    {
        return $query->whereNotNull('is_correct')->whereNotNull('nonconformity_score');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeInGroup(Builder $query, string $publisherGroup, string $scriptClass): Builder
    {
        return $query->where('publisher_group', $publisherGroup)->where('script_class', $scriptClass);
    }

    /** @return BelongsTo<ExtractionRun, self> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(ExtractionRun::class, 'extraction_run_id');
    }

    /**
     * The table cell this value sat in, when it came from one.
     *
     * Null for values taken from flat page text, which carry no row or column.
     *
     * @return BelongsTo<ExtractionTableCell, self>
     */
    public function tableCell(): BelongsTo
    {
        return $this->belongsTo(ExtractionTableCell::class, 'extraction_table_cell_id');
    }

    /** @return BelongsTo<ExtractionPage, self> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(ExtractionPage::class, 'extraction_page_id');
    }

    /** @return BelongsTo<User, self> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
