<?php

namespace App\Events;

use App\Models\Award;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContractAwarded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Award $award,
        public User $actor,
    ) {}
}
