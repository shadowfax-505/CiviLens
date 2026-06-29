<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendDomainNotification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $notificationType,
        public array $payload = [],
    ) {}

    public function handle(): void
    {
        Log::info('queue.notification.prepared', [
            'notification_type' => $this->notificationType,
        ]);
    }
}
