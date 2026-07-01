<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsReport;
use App\Services\Operations\SystemMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemMetricsController extends Controller
{
    public function __invoke(Request $request, SystemMetricsService $metrics): JsonResponse
    {
        abort_unless($request->user()?->can('viewAny', AnalyticsReport::class) === true, 403);

        return response()->json($metrics->metrics());
    }
}
