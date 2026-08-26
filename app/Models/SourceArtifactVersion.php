<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourceArtifactVersion extends Model
{
    use HasFactory;

    /**
     * One row per distinct file, keeping the earliest acquisition of each.
     *
     * The same document is published at more than one address — a listing page
     * and a document library, a ministry site and a portal that mirrors it —
     * and discovery follows both, so 75 of 673 stored artifacts are a second
     * copy of a file already held. Nothing is wrong with holding them: the
     * checksums match, both URLs are real provenance, and both are worth
     * recording.
     *
     * What must not happen is counting the same document twice where the count
     * carries meaning. A duplicated file duplicates its candidates, and a
     * calibration set drawn from a pool where one document appears twice is no
     * longer a sample of distinct documents — which is the assumption the
     * bound rests on, not an aesthetic preference. It has not bitten yet
     * because the only labelled publisher holds no duplicates; it would bite on
     * the first label judged against cbad or IMED.
     *
     * Derived from the checksum rather than stamped by a command, so a
     * duplicate acquired tomorrow is excluded without anyone remembering to
     * re-run anything.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeCanonical(Builder $query): void
    {
        $query->whereIn('id', static::query()->selectRaw('min(id)')->groupBy('sha256'));
    }

    /** Artifacts holding a file some other row already holds. @param Builder<$this> $query */
    public function scopeDuplicate(Builder $query): void
    {
        $query->whereNotIn('id', static::query()->selectRaw('min(id)')->groupBy('sha256'));
    }

    protected $fillable = [
        'discovered_resource_id',
        'source_crawl_run_id',
        'document_version_id',
        'supersedes_id',
        'version_number',
        'storage_disk',
        'storage_path',
        'original_filename',
        'media_type',
        'byte_size',
        'sha256',
        'retrieval_url',
        'http_etag',
        'http_last_modified',
        'response_headers',
        'malware_status',
        'is_quarantined',
        'quarantine_reason',
        'retrieved_at',
    ];

    protected function casts(): array
    {
        return [
            'response_headers' => 'array',
            'byte_size' => 'integer',
            'version_number' => 'integer',
            'is_quarantined' => 'boolean',
            'retrieved_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn (): bool => false);
        static::updating(fn (self $artifact): bool => array_diff(array_keys($artifact->getDirty()), ['document_version_id', 'updated_at']) === []);
    }

    /** @return BelongsTo<DiscoveredResource, self> */
    public function discoveredResource(): BelongsTo
    {
        return $this->belongsTo(DiscoveredResource::class);
    }

    public function crawlRun(): BelongsTo
    {
        return $this->belongsTo(SourceCrawlRun::class, 'source_crawl_run_id');
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    public function extractionRuns(): HasMany
    {
        return $this->hasMany(ExtractionRun::class);
    }
}
