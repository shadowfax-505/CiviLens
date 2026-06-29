<?php

namespace App\Events;

use App\Models\ContractorPerformanceSnapshot;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PerformanceSnapshotCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ContractorPerformanceSnapshot $snapshot,
    ) {}
}
