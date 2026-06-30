<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchDocument extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'indexed_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<SearchIndex, $this>
     */
    public function index(): BelongsTo
    {
        return $this->belongsTo(SearchIndex::class, 'search_index_id');
    }
}
