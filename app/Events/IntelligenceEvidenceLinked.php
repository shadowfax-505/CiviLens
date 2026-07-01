<?php

namespace App\Events;

use App\Models\IntelligenceEvidence;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IntelligenceEvidenceLinked
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public IntelligenceEvidence $evidence) {}
}
