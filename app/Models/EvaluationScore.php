<?php

namespace App\Models;

use Database\Factories\EvaluationScoreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['bid_submission_id', 'evaluation_criterion_id', 'committee_member_id', 'score', 'comments'])]
class EvaluationScore extends Model
{
    /** @use HasFactory<EvaluationScoreFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['score' => 'decimal:2'];
    }

    public function bidSubmission(): BelongsTo
    {
        return $this->belongsTo(BidSubmission::class);
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(EvaluationCriterion::class, 'evaluation_criterion_id');
    }

    public function committeeMember(): BelongsTo
    {
        return $this->belongsTo(CommitteeMember::class);
    }
}
