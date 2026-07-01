<?php

namespace App\Services\Intelligence;

use App\Models\IntelligenceIndicator;
use App\Models\IntelligenceRule;
use App\Models\User;
use Illuminate\Support\Collection;

class IntelligenceManager
{
    public function __construct(
        private readonly RuleRegistry $registry,
        private readonly RuleExecutionService $rules,
    ) {}

    /**
     * @return Collection<int, IntelligenceRule>
     */
    public function enabledRules(): Collection
    {
        return $this->registry->enabled();
    }

    /**
     * @return Collection<int, IntelligenceIndicator>
     */
    public function runRule(IntelligenceRule $rule, ?User $user = null): Collection
    {
        return $this->rules->run($rule, $user);
    }
}
