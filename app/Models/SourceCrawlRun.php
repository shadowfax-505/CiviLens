<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourceCrawlRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'source_endpoint_id',
        'triggered_by',
        'status',
        'cursor_before',
        'cursor_after',
        'discovered_count',
        'fetched_count',
        'quarantined_count',
        'failure_count',
        'error_summary',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'cursor_before' => 'array',
            'cursor_after' => 'array',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'discovered_count' => 'integer',
            'fetched_count' => 'integer',
            'quarantined_count' => 'integer',
            'failure_count' => 'integer',
        ];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(SourceEndpoint::class, 'source_endpoint_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(DiscoveredResource::class);
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(SourceArtifactVersion::class);
    }
}
