<?php

namespace App\Jobs;

use App\Services\Intelligence\IntelligenceDashboardService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshIntelligenceDashboard implements ShouldQueue
{
    use Queueable;

    public function handle(IntelligenceDashboardService $dashboard): void
    {
        $dashboard->summary();
    }
}
