<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A document as published: who published it, where it came from, and the
 * checksum that says it is the same bytes CivicLens read.
 */
class PublicationDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'publication_snapshot_id',
        'source_artifact_version_id',
        'publisher_slug',
        'publisher_name',
        'attribution_name',
        'rights_decision',
        'source_url',
        'original_filename',
        'media_type',
        'byte_size',
        'sha256',
        'page_count',
        'figure_count',
        'is_document',
        'retrieved_at',
    ];

    protected function casts(): array
    {
        return [
            'byte_size' => 'integer',
            'page_count' => 'integer',
            'figure_count' => 'integer',
            'is_document' => 'boolean',
            'retrieved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<PublicationSnapshot, $this> */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(PublicationSnapshot::class, 'publication_snapshot_id');
    }

    /** @return BelongsTo<SourceArtifactVersion, $this> */
    public function artifact(): BelongsTo
    {
        return $this->belongsTo(SourceArtifactVersion::class, 'source_artifact_version_id');
    }

    /** @return HasMany<PublicationFigure, $this> */
    public function figures(): HasMany
    {
        return $this->hasMany(PublicationFigure::class);
    }
}
