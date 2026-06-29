<?php

namespace App\Events;

use App\Models\ComplianceRecord;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ComplianceFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ComplianceRecord $complianceRecord,
    ) {}
}
