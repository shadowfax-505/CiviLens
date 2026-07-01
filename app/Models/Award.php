<?php

namespace App\Models;

use Database\Factories\AwardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['tender_id', 'bid_submission_id', 'awarded_at', 'status', 'public_disclosure_status', 'notes'])]
class Award extends Model
{
    /** @use HasFactory<AwardFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['awarded_at' => 'date'];
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function bidSubmission(): BelongsTo
    {
        return $this->belongsTo(BidSubmission::class);
    }

    public function contract(): HasOne
    {
        return $this->hasOne(Contract::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(AwardApproval::class);
    }
}
