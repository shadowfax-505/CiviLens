<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExtractionPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'extraction_run_id',
        'page_number',
        'script_class',
        'text_layer_density',
        'extraction_path',
        'confidence',
        'extracted_text',
        'recognized_words',
        'recognized_dpi',
        'tables_detected_at',
        'abstention_reason',
        'ink_coverage',
        'content_hash',
        'character_count',
        'word_count',
        'duration_ms',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'page_number' => 'integer',
            'text_layer_density' => 'float',
            'confidence' => 'float',
            'character_count' => 'integer',
            'word_count' => 'integer',
            'duration_ms' => 'integer',
            'recognized_words' => 'array',
            'recognized_dpi' => 'integer',
            'tables_detected_at' => 'datetime',
            'ink_coverage' => 'float',
        ];
    }

    /**
     * Pages of the run that currently describes each document.
     *
     * A document can be extracted more than once — after a router fix, after a
     * format learned to be read, after a failed run is retried — and every
     * attempt leaves its pages behind. Counting all of them counts the same
     * page as many times as it has been read: 11,162 rows against 9,732
     * documents' worth of pages, and 244 of the surplus were pages of runs that
     * failed and will never be read at all.
     *
     * The same discipline as the canonical-file scope on artifacts. A corpus
     * figure has to describe the corpus, not the number of times it was
     * processed.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        // One definition, in ExtractionRun. It was written out here and the
        // decision pass did not have it at all, which is how a document read
        // twice came to be certified twice.
        $query->whereIn('extraction_run_id', ExtractionRun::currentIds());
    }

    /** @return BelongsTo<ExtractionRun, self> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(ExtractionRun::class, 'extraction_run_id');
    }

    /** @return HasMany<ExtractionField, self> */
    public function fields(): HasMany
    {
        return $this->hasMany(ExtractionField::class);
    }
}
