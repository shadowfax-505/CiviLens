<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AnalyticsCacheRefreshed
{
    use Dispatchable, SerializesModels;

    public function __construct(public string $scope = 'analytics') {}
}
