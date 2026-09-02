<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExtractionRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'benchmark',
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

    /** @return BelongsTo<SourceArtifactVersion, self> */
    public function artifactVersion(): BelongsTo
    {
        return $this->belongsTo(SourceArtifactVersion::class, 'source_artifact_version_id');
    }

    /** @return BelongsTo<User, self> */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    /** @return BelongsTo<User, self> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return HasMany<ExtractionPage, self> */
    public function pages(): HasMany
    {
        return $this->hasMany(ExtractionPage::class);
    }

    /** @return HasMany<ExtractionField, self> */
    public function fields(): HasMany
    {
        return $this->hasMany(ExtractionField::class);
    }

    /**
     * The one run per artifact whose work counts: the latest that did not fail.
     *
     * Held here rather than restated at each call site, because it decides what
     * the corpus *is* and three places already needed it. A document read twice
     * has two complete sets of pages and fields, and anything that forgets to
     * ask this counts the same document twice — 9,737 accepted values sat on
     * superseded runs, and one 587-page judgment was published with fifteen
     * figures that were eight values and seven of the same values again.
     *
     * Failed runs excluded, not merely superseded ones: a run that failed left
     * its pages in whatever state it reached, and those are not pages of this
     * corpus. A run still in progress is counted, because its pages are real
     * work and excluding them would make every figure dip while the pipeline is
     * busy.
     *
     * @return Builder<self>
     */
    public static function currentIds(): Builder
    {
        return self::query()
            ->where('status', '!=', 'failed')
            ->selectRaw('max(id)')
            ->groupBy('source_artifact_version_id');
    }
}
