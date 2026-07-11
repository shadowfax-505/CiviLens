<?php

namespace App\Http\Controllers\Admin\Intelligence;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Intelligence\UpdateIntelligenceRuleRequest;
use App\Models\IntelligenceIndicator;
use App\Models\IntelligenceRule;
use App\Services\Intelligence\IntelligenceManager;
use App\Services\Intelligence\RuleManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IntelligenceRuleController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('viewAny', IntelligenceIndicator::class) === true, 403);

        return view('admin.intelligence.rules.index', [
            'rules' => IntelligenceRule::query()
                ->with(['type'])
                ->withCount('indicators')
                ->orderBy('priority')
                ->orderBy('module')
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function show(Request $request, IntelligenceRule $rule): View
    {
        abort_unless($request->user()?->can('viewAny', IntelligenceIndicator::class) === true, 403);

        return view('admin.intelligence.rules.show', [
            'rule' => $rule->load(['type', 'audits.actor']),
            'recentIndicators' => $rule->indicators()->latest('detected_at')->limit(10)->get(),
        ]);
    }

    public function update(UpdateIntelligenceRuleRequest $request, IntelligenceRule $rule, RuleManagementService $rules): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $rules->update($rule, $user, $request->ruleData());

        return redirect()
            ->route('admin.intelligence.rules.show', $rule)
            ->with('status', 'intelligence-rule-updated');
    }

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

    public function dryRun(Request $request, IntelligenceRule $rule, RuleManagementService $rules): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->can('viewAny', IntelligenceIndicator::class) === true, 403);

        $result = $rules->dryRun($rule);

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        return back()->with('status', 'Dry run for '.$rule->name.' estimated '.number_format((int) $result['estimated_matches']).' matching source record(s).');
    }
}
