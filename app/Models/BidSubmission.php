<?php

namespace App\Models;

use Database\Factories\BidSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'tender_id',
    'bidder_organization_id',
    'reference_number',
    'submitted_at',
    'bid_amount',
    'bid_valid_until',
    'bid_security_amount',
    'technical_proposal_summary',
    'financial_proposal_summary',
    'opened_at',
    'withdrawn_at',
    'technical_score',
    'financial_score',
    'status',
    'notes',
])]
class BidSubmission extends Model
{
    /** @use HasFactory<BidSubmissionFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'bid_amount' => 'decimal:2',
            'bid_valid_until' => 'date',
            'bid_security_amount' => 'decimal:2',
            'opened_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'technical_score' => 'decimal:2',
            'financial_score' => 'decimal:2',
        ];
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function bidderOrganization(): BelongsTo
    {
        return $this->belongsTo(BidderOrganization::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BidDocument::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(EvaluationScore::class);
    }

    public function award(): HasOne
    {
        return $this->hasOne(Award::class);
    }

    public function openingRecord(): HasOne
    {
        return $this->hasOne(BidOpeningRecord::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(BidWithdrawal::class);
    }

    public function complianceItems(): HasMany
    {
        return $this->hasMany(BidComplianceItem::class);
    }

    public function evaluationSummary(): HasOne
    {
        return $this->hasOne(EvaluationSummary::class);
    }
}
