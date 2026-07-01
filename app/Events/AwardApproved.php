<?php

namespace App\Events;

use App\Models\Award;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AwardApproved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Award $award, public User $actor) {}
}
