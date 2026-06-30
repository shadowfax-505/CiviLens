<?php

namespace App\Events;

use App\Models\SearchIndex;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EntityReindexed
{
    use Dispatchable, SerializesModels;

    public function __construct(public SearchIndex $index) {}
}
