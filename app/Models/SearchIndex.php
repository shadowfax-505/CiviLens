<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SearchIndex extends Model
{
    protected $table = 'search_indexes';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'entity_keywords' => 'array',
            'metadata' => 'array',
            'last_embedding_update' => 'datetime',
            'indexed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<SearchDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(SearchDocument::class);
    }

    /**
     * @return HasMany<SearchKeyword, $this>
     */
    public function keywords(): HasMany
    {
        return $this->hasMany(SearchKeyword::class);
    }

    /**
     * @return HasOne<SearchPopularity, $this>
     */
    public function popularity(): HasOne
    {
        return $this->hasOne(SearchPopularity::class);
    }

    public function source(): ?Model
    {
        $class = $this->searchable_type;

        if (! is_a($class, Model::class, true)) {
            return null;
        }

        return $class::query()->find($this->searchable_id);
    }
}
