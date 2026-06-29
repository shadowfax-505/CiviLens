<?php

namespace App\Models;

use Database\Factories\BudgetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'project_id',
    'fiscal_year_id',
    'budget_type_id',
    'funding_source_id',
    'budget_category_id',
    'budget_status_id',
    'original_allocation',
    'current_allocation',
    'reserved_amount',
    'committed_amount',
    'actual_expenditure',
    'currency',
    'notes',
    'is_active',
    'archived_at',
])]
class Budget extends Model
{
    /** @use HasFactory<BudgetFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'original_allocation' => 'decimal:2',
            'current_allocation' => 'decimal:2',
            'reserved_amount' => 'decimal:2',
            'committed_amount' => 'decimal:2',
            'actual_expenditure' => 'decimal:2',
            'is_active' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(BudgetType::class, 'budget_type_id');
    }

    public function fundingSource(): BelongsTo
    {
        return $this->belongsTo(FundingSource::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BudgetCategory::class, 'budget_category_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(BudgetStatus::class, 'budget_status_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(BudgetRevision::class)->latest('revision_number');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BudgetTransaction::class)->latest('transaction_date');
    }

    public function tenders(): HasMany
    {
        return $this->hasMany(Tender::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function getRemainingBalanceAttribute(): float
    {
        return (float) $this->current_allocation
            - (float) $this->reserved_amount
            - (float) $this->committed_amount
            - (float) $this->actual_expenditure;
    }

    public function getUtilizationPercentageAttribute(): float
    {
        if ((float) $this->current_allocation <= 0.0) {
            return 0.0;
        }

        return round(((float) $this->actual_expenditure / (float) $this->current_allocation) * 100, 2);
    }
}
