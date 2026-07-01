<?php

namespace App\Events;

use App\Models\BidSubmission;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BidOpened
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public BidSubmission $bidSubmission, public User $actor) {}
}
