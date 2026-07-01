<?php

namespace App\Events;

use App\Models\AnalyticsAlert;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AlertTriggered
{
    use Dispatchable, SerializesModels;

    public function __construct(public AnalyticsAlert $alert) {}
}
