<?php

namespace App\Events;

use App\Models\IntelligenceIndicator;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IntelligenceIndicatorDetected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public IntelligenceIndicator $indicator) {}
}
