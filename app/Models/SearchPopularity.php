<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchPopularity extends Model
{
    protected $table = 'search_popularity';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'popularity_score' => 'float',
            'last_clicked_at' => 'datetime',
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
