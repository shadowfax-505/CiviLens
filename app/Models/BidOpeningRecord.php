<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['bid_submission_id', 'opened_by', 'opened_at', 'recorded_amount', 'notes'])]
class BidOpeningRecord extends Model
{
    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'recorded_amount' => 'decimal:2',
        ];
    }

    public function bidSubmission(): BelongsTo
    {
        return $this->belongsTo(BidSubmission::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }
}
