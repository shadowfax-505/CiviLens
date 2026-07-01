<?php

namespace App\Http\Controllers;

use App\Services\Operations\SystemMetricsService;
use Illuminate\Http\JsonResponse;

class VersionController extends Controller
{
    public function __invoke(SystemMetricsService $metrics): JsonResponse
    {
        return response()->json($metrics->version());
    }
}
