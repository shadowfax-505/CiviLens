<?php

namespace App\Services\Identity;

use App\Models\User;
use Illuminate\Http\Request;

class AccountActivityLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(User $user, string $event, ?Request $request = null, ?User $actor = null, array $metadata = []): void
    {
        $user->accountActivities()->create([
            'actor_id' => $actor?->id,
            'event' => $event,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
