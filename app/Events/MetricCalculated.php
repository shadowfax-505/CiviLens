<?php

namespace App\Events;

use App\Support\Analytics\MetricResult;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MetricCalculated
{
    use Dispatchable, SerializesModels;

    public function __construct(public MetricResult $metric) {}
}
