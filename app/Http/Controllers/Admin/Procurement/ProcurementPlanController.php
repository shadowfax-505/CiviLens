<?php

namespace App\Http\Controllers\Admin\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Procurement\ProcurementPlanRequest;
use App\Models\Agency;
use App\Models\Budget;
use App\Models\FiscalYear;
use App\Models\FundingSource;
use App\Models\ProcurementMethod;
use App\Models\ProcurementPlan;
use App\Models\Project;
use App\Services\Procurement\ProcurementPlanningService;
use App\Support\Http\AuthenticatedUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProcurementPlanController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('viewAny', ProcurementPlan::class) === true, 403);

        return view('admin.procurement.plans.index', [
            'plans' => ProcurementPlan::query()->with(['agency', 'project', 'method'])->latest()->paginate(15),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('create', ProcurementPlan::class) === true, 403);

        return view('admin.procurement.plans.form', $this->lookupData());
    }

    public function store(ProcurementPlanRequest $request, ProcurementPlanningService $plans): RedirectResponse
    {
        $plan = $plans->create($request->validated(), AuthenticatedUser::from($request));

        return redirect()->route('admin.procurement.plans.show', $plan)->with('status', 'procurement-plan-created');
    }

    public function show(Request $request, ProcurementPlan $plan): View
    {
        abort_unless($request->user()?->can('view', $plan) === true, 403);

        return view('admin.procurement.plans.show', [
            'plan' => $plan->load(['agency', 'project', 'budget', 'fiscalYear', 'fundingSource', 'method', 'activities.actor']),
        ]);
    }

    public function approve(Request $request, ProcurementPlan $plan, ProcurementPlanningService $plans): RedirectResponse
    {
        abort_unless($request->user()?->can('update', $plan) === true, 403);
        $plans->approve($plan, AuthenticatedUser::from($request), (string) $request->input('approval_notes', 'Approved.'));

        return back()->with('status', 'procurement-plan-approved');
    }

    /**
     * @return array<string, mixed>
     */
    private function lookupData(): array
    {
        return [
            'agencies' => Agency::query()->orderBy('name')->get(),
            'budgets' => Budget::query()->with(['project', 'fiscalYear'])->latest()->get(),
            'projects' => Project::query()->orderBy('name')->get(),
            'fiscalYears' => FiscalYear::query()->orderByDesc('starts_on')->get(),
            'fundingSources' => FundingSource::query()->orderBy('name')->get(),
            'methods' => ProcurementMethod::query()->orderBy('name')->get(),
        ];
    }
}
