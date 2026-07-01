<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['bid_submission_id', 'technical_score', 'financial_score', 'compliance_score', 'overall_score', 'recommendation', 'finalized_by', 'finalized_at', 'score_payload'])]
class EvaluationSummary extends Model
{
    protected function casts(): array
    {
        return [
            'technical_score' => 'decimal:2',
            'financial_score' => 'decimal:2',
            'compliance_score' => 'decimal:2',
            'overall_score' => 'decimal:2',
            'finalized_at' => 'datetime',
            'score_payload' => 'array',
        ];
    }

    public function bidSubmission(): BelongsTo
    {
        return $this->belongsTo(BidSubmission::class);
    }
}
