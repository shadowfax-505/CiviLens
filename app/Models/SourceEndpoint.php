<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourceEndpoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_publisher_id',
        'name',
        'connector_type',
        'connector_options',
        'base_url',
        'allowed_hosts',
        'allowed_path_prefixes',
        'access_decision',
        'access_reviewed_at',
        'crawl_interval_minutes',
        'rate_limit_per_minute',
        'timeout_seconds',
        'max_content_bytes',
        'cursor',
        'health_status',
        'last_error',
        'last_crawled_at',
        'paused_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'connector_options' => 'array',
            'allowed_hosts' => 'array',
            'allowed_path_prefixes' => 'array',
            'access_reviewed_at' => 'immutable_datetime',
            'cursor' => 'array',
            'last_crawled_at' => 'immutable_datetime',
            'paused_at' => 'immutable_datetime',
            'crawl_interval_minutes' => 'integer',
            'rate_limit_per_minute' => 'integer',
            'timeout_seconds' => 'integer',
            'max_content_bytes' => 'integer',
        ];
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(SourcePublisher::class, 'source_publisher_id');
    }

    public function crawlRuns(): HasMany
    {
        return $this->hasMany(SourceCrawlRun::class);
    }

    public function discoveredResources(): HasMany
    {
        return $this->hasMany(DiscoveredResource::class);
    }

    public function isPaused(): bool
    {
        return $this->paused_at !== null;
    }

    public function isDue(): bool
    {
        $lastCrawledAt = $this->getAttribute('last_crawled_at');

        return ! $lastCrawledAt instanceof CarbonInterface
            || $lastCrawledAt->addMinutes($this->crawl_interval_minutes)->isPast();
    }

    /**
     * Per-connector settings, always an array so a caller need not guess.
     *
     * @return array<string, mixed>
     */
    public function connectorOptions(): array
    {
        $options = $this->connector_options;

        return is_array($options) ? $options : [];
    }
}
