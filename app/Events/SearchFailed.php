<?php

namespace App\Events;

use App\Models\User;
use App\Support\Search\SearchQuery;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SearchFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public SearchQuery $query,
        public User $user,
        public Throwable $exception,
    ) {}
}
