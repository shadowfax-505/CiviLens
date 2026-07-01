<?php

namespace App\Events;

use App\Models\CitizenReport;
use App\Models\CitizenReportStatus;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CitizenReportStatusChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public CitizenReport $report, public CitizenReportStatus $status) {}
}
