<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SuggestionGenerated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, string>  $suggestions
     */
    public function __construct(
        public string $prefix,
        public User $user,
        public array $suggestions,
    ) {}
}
