<?php

namespace App\Events;

use App\Models\Tender;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenderPublished
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Tender $tender,
        public User $actor,
    ) {}
}
