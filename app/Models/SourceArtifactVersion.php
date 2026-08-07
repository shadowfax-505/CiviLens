<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourceArtifactVersion extends Model
{
    use HasFactory;

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
