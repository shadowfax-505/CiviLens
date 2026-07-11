<?php

namespace App\Jobs;

use App\Mail\CitizenReportAcknowledgement;
use App\Models\CitizenReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class NotifyCitizenReportSubmitted implements ShouldQueue
{
    use Queueable;

    public function __construct(public CitizenReport $report) {}

    public function handle(): void
    {
        $this->report->loadMissing('submitter');

        Mail::to($this->report->submitter)->queue(new CitizenReportAcknowledgement($this->report));
    }
}
