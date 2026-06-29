<?php

namespace App\Jobs;

use App\Events\RiskScoreUpdated;
use App\Models\ContractorProfile;
use App\Services\Contractors\ContractorScoreService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecalculateContractorRiskScore implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $contractorProfileId,
    ) {}

    public function handle(ContractorScoreService $scoreService): void
    {
        $profile = ContractorProfile::query()->find($this->contractorProfileId);

        if (! $profile instanceof ContractorProfile) {
            return;
        }

        RiskScoreUpdated::dispatch($profile, $scoreService->calculate($profile));
    }
}
