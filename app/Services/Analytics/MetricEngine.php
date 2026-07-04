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

        $cached = Cache::remember(
            $cacheKey,
            now()->addMinutes(5),
            fn (): array => $this->registry->calculate($key, $filters)->toArray(),
        );

        if ($cached instanceof MetricResult) {
            $metric = $cached;
            Cache::put($cacheKey, $metric->toArray(), now()->addMinutes(5));
        } elseif (is_array($cached)) {
            $metric = new MetricResult(
                key: (string) $cached['key'],
                label: (string) $cached['label'],
                category: (string) $cached['category'],
                value: $cached['value'],
                unit: $cached['unit'] ?? null,
                description: $cached['description'] ?? null,
                meta: $cached['meta'] ?? [],
            );
        } else {
            Cache::forget($cacheKey);
            $metric = $this->registry->calculate($key, $filters);
            Cache::put($cacheKey, $metric->toArray(), now()->addMinutes(5));
        }

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
