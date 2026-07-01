<?php

namespace App\Models;

use Database\Factories\VariationOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['contract_id', 'title', 'description', 'approved_at', 'approved_amount', 'schedule_extension_days', 'reason', 'status'])]
class VariationOrder extends Model
{
    /** @use HasFactory<VariationOrderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'approved_at' => 'date',
            'approved_amount' => 'decimal:2',
            'schedule_extension_days' => 'integer',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
