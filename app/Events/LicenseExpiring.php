<?php

namespace App\Events;

use App\Models\ContractorLicense;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LicenseExpiring
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ContractorLicense $license,
    ) {}
}
