<?php

namespace App\Services\Analytics;

use App\Events\MetricCalculated;
use App\Support\Analytics\AnalyticsFilters;
use App\Support\Analytics\MetricResult;
use Illuminate\Support\Facades\Cache;

class MetricEngine
{
    public function __construct(private readonly MetricRegistry $registry) {}

    public function calculate(string $key, AnalyticsFilters $filters): MetricResult
    {
        $cacheKey = 'analytics:metric:'.md5($key.'|'.$filters->hash());

        $metric = Cache::remember($cacheKey, now()->addMinutes(5), fn (): MetricResult => $this->registry->calculate($key, $filters));

        MetricCalculated::dispatch($metric);

        return $metric;
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, array<string, mixed>>
     */
    public function calculateMany(array $keys, AnalyticsFilters $filters): array
    {
        return collect($keys)
            ->mapWithKeys(fn (string $key): array => [$key => $this->calculate($key, $filters)->toArray()])
            ->all();
    }
}
