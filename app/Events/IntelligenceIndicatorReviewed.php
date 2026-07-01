<?php

namespace App\Events;

use App\Models\IntelligenceIndicator;
use App\Models\IntelligenceReview;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IntelligenceIndicatorReviewed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public IntelligenceIndicator $indicator, public IntelligenceReview $review) {}
}
