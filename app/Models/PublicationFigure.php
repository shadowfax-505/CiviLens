<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One certified value, published with the page it came from and the level it
 * was certified at.
 *
 * Only accepted fields become figures. A deferred field is one the system
 * declined to stand behind, and publishing it with a caveat would be
 * publishing it.
 */
class PublicationFigure extends Model
{
    use HasFactory;

    protected $fillable = [
        'publication_snapshot_id',
        'publication_document_id',
        'extraction_field_id',
        'field_kind',
        'field_type',
        'extracted_value',
        'normalized_value',
        'page_number',
        'script_class',
        'certified_alpha',
        'decision_basis',
        'publisher_group',
        'nonconformity_score',
    ];

    protected function casts(): array
    {
        return [
            'page_number' => 'integer',
            'certified_alpha' => 'float',
            'nonconformity_score' => 'float',
        ];
    }

    /**
     * Whether the guarantee behind this figure is conditional on its own group.
     *
     * ADR-018: 'group' is the publisher-and-script conditional case. A threshold
     * borrowed from a wider pool ('script', 'marginal') is valid for that pool
     * and is not a statement about the rare group inside it. Anything reported
     * as group-conditional must key on this rather than on the level alone.
     */
    public function isConditional(): bool
    {
        return $this->decision_basis === 'group';
    }

    /** @return BelongsTo<PublicationDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(PublicationDocument::class, 'publication_document_id');
    }

    /** @return BelongsTo<ExtractionField, $this> */
    public function field(): BelongsTo
    {
        return $this->belongsTo(ExtractionField::class, 'extraction_field_id');
    }
}
