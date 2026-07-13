<?php

namespace App\Models;

use App\Contracts\Search\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model implements Searchable
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
            'file_size' => 'integer',
            'page_count' => 'integer',
            'version_number' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<DocumentType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    /**
     * @return BelongsTo<DocumentCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    /**
     * @return BelongsTo<DocumentStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(DocumentStatus::class, 'document_status_id');
    }

    /**
     * @return BelongsTo<DocumentVisibility, $this>
     */
    public function visibility(): BelongsTo
    {
        return $this->belongsTo(DocumentVisibility::class, 'document_visibility_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return HasMany<DocumentVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    /**
     * @return HasOne<DocumentVersion, $this>
     */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class)->where('is_current', true);
    }

    /**
     * @return HasMany<Documentable, $this>
     */
    public function documentables(): HasMany
    {
        return $this->hasMany(Documentable::class);
    }

    /**
     * @return BelongsToMany<DocumentTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(DocumentTag::class, 'document_tag')->withTimestamps();
    }

    /**
     * @return HasMany<DocumentActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(DocumentActivity::class)->latest();
    }

    public function searchTitle(): string
    {
        return $this->title;
    }

    public function searchDescription(): ?string
    {
        return $this->description;
    }

    public function searchKeywords(): array
    {
        return array_values(array_filter(array_merge([
            $this->uuid,
            $this->title,
            $this->original_filename,
            $this->file_extension,
            $this->mime_type,
            $this->type?->name,
            $this->category?->name,
            $this->status?->name,
            $this->visibility?->name,
            $this->language,
        ], $this->tags()->pluck('name')->all())));
    }

    public function searchRelations(): array
    {
        return [
            'attached_to' => $this->documentables()->get()->map(fn (Documentable $documentable): array => [
                'type' => $documentable->documentable_type,
                'id' => $documentable->documentable_id,
                'relationship' => $documentable->relationship_type,
            ])->all(),
        ];
    }

    public function searchModule(): string
    {
        return 'documents';
    }

    public function searchUrl(): string
    {
        return route('admin.documents.show', $this, false);
    }

    public function publicSearchUrl(): string
    {
        return route('public.documents.download', $this, false);
    }

    public function searchStatus(): ?string
    {
        return $this->status?->slug;
    }

    public function searchVisibility(): string
    {
        return $this->visibility?->slug ?? 'private';
    }

    public function searchMetadata(): array
    {
        return [
            'route_module' => 'documents',
            'uuid' => $this->uuid,
            'document_type_id' => $this->document_type_id,
            'document_category_id' => $this->document_category_id,
            'file_extension' => $this->file_extension,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'version_number' => $this->version_number,
            'ocr_status' => $this->ocr_status,
            'index_status' => $this->index_status,
            'public_url' => $this->publicSearchUrl(),
        ];
    }
}
