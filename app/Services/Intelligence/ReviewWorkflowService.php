<?php

namespace App\Services\Intelligence;

use App\Events\IntelligenceIndicatorReviewed;
use App\Models\IntelligenceActivity;
use App\Models\IntelligenceIndicator;
use App\Models\IntelligenceReview;
use App\Models\User;

class ReviewWorkflowService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function review(IntelligenceIndicator $indicator, User $reviewer, array $data): IntelligenceReview
    {
        $review = IntelligenceReview::query()->create([
            'intelligence_indicator_id' => $indicator->id,
            'reviewed_by' => $reviewer->id,
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
            'reviewed_at' => now(),
            'follow_up_on' => $data['follow_up_on'] ?? null,
        ]);

        $indicator->update([
            'status' => $data['status'],
            'updated_by' => $reviewer->id,
        ]);

        IntelligenceActivity::query()->create([
            'intelligence_indicator_id' => $indicator->id,
            'actor_id' => $reviewer->id,
            'event' => 'indicator_reviewed',
            'description' => 'Indicator reviewed as '.$data['status'].'.',
            'properties' => ['review_id' => $review->id],
            'occurred_at' => now(),
        ]);

        IntelligenceIndicatorReviewed::dispatch($indicator, $review);

        return $review;
    }
}
