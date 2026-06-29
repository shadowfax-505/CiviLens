<?php

namespace App\Models;

use Database\Factories\ContractExtensionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['contract_id', 'previous_end_date', 'new_end_date', 'reason', 'approved_at'])]
class ContractExtension extends Model
{
    /** @use HasFactory<ContractExtensionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'previous_end_date' => 'date',
            'new_end_date' => 'date',
            'approved_at' => 'date',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
