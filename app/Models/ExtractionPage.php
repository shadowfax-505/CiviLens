<?php

namespace App\Models;

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
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ExtractionRun::class, 'extraction_run_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(ExtractionField::class);
    }
}
