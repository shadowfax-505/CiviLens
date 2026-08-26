<?php

namespace App\Models;

use App\Services\Extraction\WordDocumentConverter;
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

    /**
     * Whether the page images shown for this document are our rendering
     * rather than the publisher's own file.
     *
     * A PDF page image is the publisher's artefact rasterised: what a reader
     * sees is what the publisher published. A Word document has no pages until
     * something lays it out, so its page images are LibreOffice's rendering,
     * and the layout may not be the one the publisher saw. The content is the
     * publisher's; the appearance is ours, and a page that says "go and look"
     * has to say which of the two it is showing.
     *
     * Derived from the media type copied into the snapshot, so an old citation
     * keeps the answer that was true when it was published.
     */
    public function pageImagesAreConverted(): bool
    {
        return in_array(strtolower($this->media_type), WordDocumentConverter::MEDIA_TYPES, true);
    }
}
