<?php

namespace App\Events;

use App\Models\ProcurementPlan;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProcurementPlanApproved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public ProcurementPlan $plan, public User $actor) {}
}
