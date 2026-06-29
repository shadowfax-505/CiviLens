<?php

namespace App\Events;

use App\Models\ContractorProfile;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RiskScoreUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, float|int>  $scores
     */
    public function __construct(
        public ContractorProfile $contractorProfile,
        public array $scores,
    ) {}
}
