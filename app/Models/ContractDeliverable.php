<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['contract_id', 'contract_milestone_id', 'title', 'description', 'due_date', 'accepted_at', 'accepted_by', 'status', 'evidence_summary'])]
class ContractDeliverable extends Model
{
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'accepted_at' => 'datetime',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
