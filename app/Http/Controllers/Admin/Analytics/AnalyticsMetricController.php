<?php

namespace App\Http\Controllers\Admin\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Analytics\AnalyticsFilterRequest;
use App\Models\AnalyticsReport;
use App\Services\Analytics\MetricEngine;
use Illuminate\Http\JsonResponse;

class AnalyticsMetricController extends Controller
{
    public function __invoke(AnalyticsFilterRequest $request, MetricEngine $metrics): JsonResponse
    {
        abort_unless($request->user()?->can('viewAny', AnalyticsReport::class) === true, 403);

        $metric = $metrics->calculate((string) $request->validated('metric'), $request->filters());

        return response()->json($metric->toArray());
    }
}
