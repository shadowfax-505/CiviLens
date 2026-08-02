<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DiscoveredResource extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_endpoint_id',
        'source_crawl_run_id',
        'canonical_url',
        'canonical_url_hash',
        'discovery_url',
        'external_id',
        'resource_type',
        'status',
        'published_at',
        'last_seen_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'published_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
        ];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(SourceEndpoint::class, 'source_endpoint_id');
    }

    public function crawlRun(): BelongsTo
    {
        return $this->belongsTo(SourceCrawlRun::class, 'source_crawl_run_id');
    }

    public function artifactVersions(): HasMany
    {
        return $this->hasMany(SourceArtifactVersion::class);
    }

    public function latestArtifactVersion(): HasOne
    {
        return $this->hasOne(SourceArtifactVersion::class)->ofMany('version_number', 'max');
    }
}
