<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PublicSearchExecuted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public string $query, public int $resultCount) {}
}
