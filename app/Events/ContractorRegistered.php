<?php

namespace App\Events;

use App\Models\ContractorProfile;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContractorRegistered
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ContractorProfile $contractorProfile,
    ) {}
}
