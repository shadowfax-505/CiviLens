<?php

namespace App\Models;

use Database\Factories\BudgetRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['budget_id', 'revision_number', 'previous_allocation', 'new_allocation', 'difference', 'reason', 'approval_date', 'approved_by'])]
class BudgetRevision extends Model
{
    /** @use HasFactory<BudgetRevisionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'previous_allocation' => 'decimal:2',
            'new_allocation' => 'decimal:2',
            'difference' => 'decimal:2',
            'approval_date' => 'date',
        ];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
