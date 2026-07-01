<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InsightGenerated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, array<string, mixed>>  $insights
     */
    public function __construct(public string $dashboard, public array $insights) {}
}
