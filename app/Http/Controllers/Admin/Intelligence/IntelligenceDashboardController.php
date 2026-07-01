<?php

namespace App\Http\Controllers\Admin\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\IntelligenceIndicator;
use App\Models\IntelligenceRule;
use App\Services\Intelligence\IntelligenceDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IntelligenceDashboardController extends Controller
{
    public function __invoke(Request $request, IntelligenceDashboardService $dashboard): View
    {
        abort_unless($request->user()?->can('viewAny', IntelligenceIndicator::class) === true, 403);

        return view('admin.intelligence.dashboard', [
            'summary' => $dashboard->summary(),
            'rules' => IntelligenceRule::query()->with('type')->orderBy('module')->orderBy('name')->get(),
        ]);
    }

    public function summary(Request $request, IntelligenceDashboardService $dashboard): JsonResponse
    {
        abort_unless($request->user()?->can('viewAny', IntelligenceIndicator::class) === true, 403);

        return response()->json($dashboard->summary());
    }
}
