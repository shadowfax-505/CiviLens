<?php

namespace App\Events;

use App\Models\ContractorCertification;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CertificationExpiring
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ContractorCertification $certification,
    ) {}
}
