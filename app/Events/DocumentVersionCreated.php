<?php

namespace App\Events;

use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentVersionCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public DocumentVersion $version,
        public User $actor,
    ) {}
}
