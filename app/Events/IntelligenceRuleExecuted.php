<?php

namespace App\Events;

use App\Models\IntelligenceRule;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IntelligenceRuleExecuted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public IntelligenceRule $rule, public int $indicatorCount) {}
}
