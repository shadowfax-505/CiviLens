<?php

namespace App\Models;

use Database\Factories\LiquidatedDamageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['contract_id', 'reason', 'assessed_at', 'days_delayed', 'assessed_amount'])]
class LiquidatedDamage extends Model
{
    /** @use HasFactory<LiquidatedDamageFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'assessed_at' => 'date',
            'assessed_amount' => 'decimal:2',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
