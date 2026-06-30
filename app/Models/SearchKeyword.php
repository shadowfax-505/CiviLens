<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchKeyword extends Model
{
    protected $guarded = [];

    /**
     * @return BelongsTo<SearchIndex, $this>
     */
    public function index(): BelongsTo
    {
        return $this->belongsTo(SearchIndex::class, 'search_index_id');
    }
}
