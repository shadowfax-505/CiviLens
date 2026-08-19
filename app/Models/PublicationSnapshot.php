<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One frozen set of everything CivicLens is prepared to show the public.
 *
 * Building one and publishing it are separate acts. A snapshot can be built,
 * inspected, and thrown away; only publishing puts it in front of anyone.
 */
class PublicationSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'requested_alpha',
        'calibration_digest',
        'document_count',
        'figure_count',
        'notice_count',
        'skipped_figure_count',
        'notes',
        'built_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_alpha' => 'float',
            'document_count' => 'integer',
            'figure_count' => 'integer',
            'notice_count' => 'integer',
            'skipped_figure_count' => 'integer',
            'built_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * The snapshot the public site serves: the most recently published one.
     *
     * Publishing a new snapshot supersedes the old one without deleting it, so
     * a citation of an earlier snapshot stays resolvable.
     */
    public static function current(): ?self
    {
        return self::query()
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /** @return HasMany<PublicationDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(PublicationDocument::class);
    }

    /** @return HasMany<PublicationFigure, $this> */
    public function figures(): HasMany
    {
        return $this->hasMany(PublicationFigure::class);
    }

    /** @return HasMany<PublicationNotice, $this> */
    public function notices(): HasMany
    {
        return $this->hasMany(PublicationNotice::class);
    }
}
