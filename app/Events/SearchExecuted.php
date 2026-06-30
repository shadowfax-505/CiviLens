<?php

namespace App\Events;

use App\Models\User;
use App\Support\Search\SearchQuery;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SearchExecuted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public SearchQuery $query,
        public User $user,
        public int $resultsCount,
        public int $latencyMs,
    ) {}
}
