<?php

namespace App\Events;

use App\Models\IntelligenceProcessingJob;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IntelligenceProcessingJobQueued
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public IntelligenceProcessingJob $job) {}
}
