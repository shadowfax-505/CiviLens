<?php

namespace App\Http\Controllers\Admin\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\IntelligenceIndicator;
use App\Models\IntelligenceRule;
use App\Services\Intelligence\IntelligenceManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class IntelligenceRuleController extends Controller
{
    public function run(Request $request, IntelligenceRule $rule, IntelligenceManager $manager): RedirectResponse
    {
        abort_unless($request->user()?->can('create', IntelligenceIndicator::class) === true, 403);

        $indicators = $manager->runRule($rule, $request->user());

        return back()->with('status', $indicators->count().' indicator(s) generated.');
    }

    public function preview(Request $request, IntelligenceRule $rule): JsonResponse
    {
        abort_unless($request->user()?->can('viewAny', IntelligenceIndicator::class) === true, 403);

        return response()->json([
            'rule' => $rule->only(['id', 'name', 'slug', 'module', 'category', 'severity_default', 'version']),
            'thresholds' => $rule->thresholds ?? [],
            'configuration' => $rule->configuration ?? [],
        ]);
    }
}
