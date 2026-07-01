<?php

namespace App\Jobs;

use App\Models\CitizenReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyCitizenReportStatusChanged implements ShouldQueue
{
    use Queueable;

    public function __construct(public CitizenReport $report) {}

    public function handle(): void {}
}
