<?php

namespace App\Support\Observability;

use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

class PerformanceTimer
{
    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @param  array<string, mixed>  $context
     * @return TReturn
     *
     * @throws Throwable
     */
    public static function measure(string $operation, Closure $callback, array $context = []): mixed
    {
        $startedAt = microtime(true);

        try {
            return $callback();
        } finally {
            Log::info('performance.measurement', array_merge($context, [
                'operation' => $operation,
                'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            ]));
        }
    }
}
