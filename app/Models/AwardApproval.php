<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['award_id', 'approved_by', 'status', 'notes', 'approved_at'])]
class AwardApproval extends Model
{
    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function award(): BelongsTo
    {
        return $this->belongsTo(Award::class);
    }
}
