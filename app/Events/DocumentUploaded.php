<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentUploaded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $disk,
        public string $path,
        public User $actor,
        public ?string $documentType = null,
    ) {}
}
