<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;

class LogDomainEvent
{
    public function handle(object $event): void
    {
        Log::info('domain.event', [
            'event' => $event::class,
        ]);
    }
}
