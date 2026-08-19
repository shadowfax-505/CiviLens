<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tender notice as the publisher listed it.
 *
 * These carry no extracted values and no certification, because e-GP answers
 * with a listing row rather than a document (ADR-020). They are published as
 * observations of what was listed, never as contract records: the row has no
 * buyer, no supplier and no value, and inventing those to fill a domain table
 * would be fabrication.
 */
class PublicationNotice extends Model
{
    use HasFactory;

    protected $fillable = [
        'publication_snapshot_id',
        'tender_observation_id',
        'publisher_slug',
        'external_id',
        'reference_number',
        'status',
        'procurement_nature',
        'published_on',
        'source_url',
    ];

    protected function casts(): array
    {
        return [
            'published_on' => 'datetime',
        ];
    }

    /** @return BelongsTo<PublicationSnapshot, $this> */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(PublicationSnapshot::class, 'publication_snapshot_id');
    }

    /** @return BelongsTo<TenderObservation, $this> */
    public function observation(): BelongsTo
    {
        return $this->belongsTo(TenderObservation::class, 'tender_observation_id');
    }
}
