<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class NotifyExpiringContractorCredential implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $credentialType,
        public int $credentialId,
    ) {}

    public function handle(): void
    {
        Log::info('queue.contractor_credential_expiry.prepared', [
            'credential_type' => $this->credentialType,
            'credential_id' => $this->credentialId,
        ]);
    }
}
