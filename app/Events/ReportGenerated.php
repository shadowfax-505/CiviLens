<?php

namespace App\Events;

use App\Models\AnalyticsReport;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReportGenerated
{
    use Dispatchable, SerializesModels;

    public function __construct(public AnalyticsReport $report) {}
}
