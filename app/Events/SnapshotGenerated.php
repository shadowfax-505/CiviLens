<?php

namespace App\Events;

use App\Models\AnalyticsSnapshot;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SnapshotGenerated
{
    use Dispatchable, SerializesModels;

    public function __construct(public AnalyticsSnapshot $snapshot) {}
}
