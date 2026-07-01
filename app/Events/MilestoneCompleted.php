<?php

namespace App\Events;

use App\Models\ContractMilestone;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MilestoneCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public ContractMilestone $milestone, public User $actor) {}
}
