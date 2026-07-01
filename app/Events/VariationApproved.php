<?php

namespace App\Events;

use App\Models\User;
use App\Models\VariationOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VariationApproved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public VariationOrder $variationOrder, public User $actor) {}
}
