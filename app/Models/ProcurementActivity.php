<?php

namespace App\Models;

use Database\Factories\ProcurementActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tender_id', 'contract_id', 'actor_id', 'event', 'description', 'old_values', 'new_values'])]
class ProcurementActivity extends Model
{
    /** @use HasFactory<ProcurementActivityFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(fn (): false => false);
    }

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
