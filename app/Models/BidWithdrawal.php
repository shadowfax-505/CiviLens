<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['bid_submission_id', 'withdrawn_by', 'withdrawn_at', 'reason'])]
class BidWithdrawal extends Model
{
    protected function casts(): array
    {
        return ['withdrawn_at' => 'datetime'];
    }

    public function bidSubmission(): BelongsTo
    {
        return $this->belongsTo(BidSubmission::class);
    }
}
