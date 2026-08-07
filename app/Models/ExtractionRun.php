<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExtractionRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'source_artifact_version_id',
        'triggered_by',
        'status',
        'routing_decision',
        'engine',
        'engine_version',
        'config_hash',
        'language_hint',
        'page_count',
        'pages_native',
        'pages_ocr_primary',
        'pages_ocr_enhanced',
        'pages_abstained',
        'duration_ms',
        'peak_memory_bytes',
        'failure_reason',
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'page_count' => 'integer',
            'pages_native' => 'integer',
            'pages_ocr_primary' => 'integer',
            'pages_ocr_enhanced' => 'integer',
            'pages_abstained' => 'integer',
            'duration_ms' => 'integer',
            'peak_memory_bytes' => 'integer',
            'reviewed_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function artifactVersion(): BelongsTo
    {
        return $this->belongsTo(SourceArtifactVersion::class, 'source_artifact_version_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(ExtractionPage::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(ExtractionField::class);
    }
}
