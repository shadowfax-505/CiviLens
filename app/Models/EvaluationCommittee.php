<?php

namespace App\Models;

use Database\Factories\EvaluationCommitteeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tender_id', 'name', 'formed_at', 'status'])]
class EvaluationCommittee extends Model
{
    /** @use HasFactory<EvaluationCommitteeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['formed_at' => 'date'];
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(CommitteeMember::class);
    }
}
