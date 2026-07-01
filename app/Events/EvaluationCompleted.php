<?php

namespace App\Events;

use App\Models\EvaluationSummary;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EvaluationCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public EvaluationSummary $summary, public User $actor) {}
}
