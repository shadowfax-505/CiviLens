<?php

namespace App\Models;

use Database\Factories\BudgetTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['budget_id', 'budget_transaction_type_id', 'user_id', 'amount', 'transaction_date', 'description'])]
class BudgetTransaction extends Model
{
    /** @use HasFactory<BudgetTransactionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn (): bool => false);
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(BudgetTransactionType::class, 'budget_transaction_type_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
