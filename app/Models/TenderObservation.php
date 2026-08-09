<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenderObservation extends Model
{
    protected $fillable = [
        'source_publisher_id', 'discovered_resource_id', 'external_id',
        'reference_number', 'status', 'procurement_nature',
        'published_on_raw', 'published_on', 'observation_hash', 'observed_at',
    ];

    protected function casts(): array
    {
        return [
            'published_on' => 'immutable_date',
            'observed_at' => 'immutable_datetime',
        ];
    }

    /**
     * An observation records what a publisher said, so it cannot be edited or
     * deleted. A correction is a new observation, not a rewrite of the old one.
     */
    protected static function booted(): void
    {
        static::deleting(fn (): bool => false);
        static::updating(fn (): bool => false);
    }

    /** @return BelongsTo<SourcePublisher, self> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(SourcePublisher::class, 'source_publisher_id');
    }

    /** @return BelongsTo<DiscoveredResource, self> */
    public function discoveredResource(): BelongsTo
    {
        return $this->belongsTo(DiscoveredResource::class);
    }
}
