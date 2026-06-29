<?php

namespace App\Models;

use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['award_id', 'bid_submission_id', 'project_id', 'budget_id', 'contract_number', 'title', 'status', 'signed_at', 'start_date', 'end_date', 'notes', 'archived_at'])]
class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'signed_at' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function award(): BelongsTo
    {
        return $this->belongsTo(Award::class);
    }

    public function bidSubmission(): BelongsTo
    {
        return $this->belongsTo(BidSubmission::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ContractMilestone::class);
    }

    public function variationOrders(): HasMany
    {
        return $this->hasMany(VariationOrder::class);
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(ContractExtension::class);
    }

    public function liquidatedDamages(): HasMany
    {
        return $this->hasMany(LiquidatedDamage::class);
    }

    public function completionCertificates(): HasMany
    {
        return $this->hasMany(CompletionCertificate::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ProcurementActivity::class)->latest();
    }

    public function getTenderAttribute(): ?Tender
    {
        return $this->award?->tender;
    }
}
