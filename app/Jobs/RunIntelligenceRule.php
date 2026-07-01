<?php

namespace App\Jobs;

use App\Models\IntelligenceRule;
use App\Services\Intelligence\IntelligenceManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunIntelligenceRule implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $ruleId) {}

    public function handle(IntelligenceManager $manager): void
    {
        $rule = IntelligenceRule::query()->find($this->ruleId);

        if ($rule instanceof IntelligenceRule) {
            $manager->runRule($rule);
        }
    }
}
