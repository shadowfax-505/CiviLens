<?php

namespace App\Events;

use App\Models\CitizenReport;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CitizenReportArchived
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public CitizenReport $report) {}
}
