<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceActivity extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'source_publisher_id',
        'source_endpoint_id',
        'source_crawl_run_id',
        'source_artifact_version_id',
        'actor_id',
        'event',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn (): bool => false);
        static::updating(fn (): bool => false);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
