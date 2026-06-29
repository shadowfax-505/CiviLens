<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
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
}
