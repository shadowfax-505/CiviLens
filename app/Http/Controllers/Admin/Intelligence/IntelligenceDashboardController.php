<?php

namespace App\Http\Controllers\Admin\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\IntelligenceIndicator;
use App\Models\IntelligenceRule;
use App\Services\Intelligence\CivicIntegrityEngineService;
use App\Services\Intelligence\IntelligenceDashboardService;
use App\Support\Http\AuthenticatedUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

    public function runEngine(Request $request, CivicIntegrityEngineService $engine): RedirectResponse
    {
        abort_unless($request->user()?->can('create', IntelligenceIndicator::class) === true, 403);

        $run = $engine->run(AuthenticatedUser::from($request));

        return back()->with('status', 'civic-integrity-engine-run-'.$run->id);
    }
}
