<?php

namespace App\Http\Controllers;

use App\Services\Operations\SystemMetricsService;
use Illuminate\Http\JsonResponse;

class HealthCheckController extends Controller
{
    public function __invoke(SystemMetricsService $metrics): JsonResponse
    {
        $payload = $metrics->health();

        return response()->json($payload, $payload['status'] === 'ok' ? 200 : 503);
    }
}
