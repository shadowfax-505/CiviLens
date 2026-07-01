<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DashboardViewed
{
    use Dispatchable, SerializesModels;

    public function __construct(public string $dashboard, public ?User $user = null) {}
}
