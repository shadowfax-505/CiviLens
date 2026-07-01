<?php

namespace App\Models;

use Database\Factories\ContractMilestoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['contract_id', 'title', 'due_date', 'completed_at', 'status', 'completion_percentage', 'accepted_by', 'accepted_at', 'evidence_summary', 'notes'])]
class ContractMilestone extends Model
{
    /** @use HasFactory<ContractMilestoneFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'date',
            'completion_percentage' => 'integer',
            'accepted_at' => 'datetime',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
