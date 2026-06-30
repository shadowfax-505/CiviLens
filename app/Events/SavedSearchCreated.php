<?php

namespace App\Events;

use App\Models\SavedSearch;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SavedSearchCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public SavedSearch $savedSearch) {}
}
