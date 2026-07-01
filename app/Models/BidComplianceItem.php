<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['bid_submission_id', 'requirement', 'status', 'notes'])]
class BidComplianceItem extends Model
{
    public function bidSubmission(): BelongsTo
    {
        return $this->belongsTo(BidSubmission::class);
    }
}
