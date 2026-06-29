<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Finance\BudgetRequest;
use App\Models\Agency;
use App\Models\Budget;
use App\Models\BudgetCategory;
use App\Models\BudgetStatus;
use App\Models\BudgetType;
use App\Models\FiscalYear;
use App\Models\FundingSource;
use App\Models\Project;
use App\Services\Finance\BudgetCalculationService;
use App\Services\Finance\BudgetLifecycleService;
use App\Services\Finance\BudgetListingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function index(Request $request, BudgetListingService $listing, BudgetCalculationService $calculations): View
    {
        abort_unless($request->user()?->can('viewAny', Budget::class) === true, 403);

        return view('admin.finance.budgets.index', array_merge($this->lookupData(), [
            'budgets' => $listing->paginate($request),
            'summary' => $calculations->dashboardSummary(),
            'archived' => false,
        ]));
    }

    public function archived(Request $request, BudgetListingService $listing, BudgetCalculationService $calculations): View
    {
        abort_unless($request->user()?->can('viewAny', Budget::class) === true, 403);

        return view('admin.finance.budgets.index', array_merge($this->lookupData(), [
            'budgets' => $listing->paginate($request, archived: true),
            'summary' => $calculations->dashboardSummary(),
            'archived' => true,
        ]));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('create', Budget::class) === true, 403);

        return view('admin.finance.budgets.form', array_merge($this->lookupData(), [
            'budget' => new Budget,
        ]));
    }

    public function store(BudgetRequest $request, BudgetLifecycleService $service): RedirectResponse
    {
        $budget = $service->create($request->validated());

        return redirect()->route('admin.finance.budgets.show', $budget)->with('status', 'budget-created');
    }

    public function show(Request $request, Budget $budget): View
    {
        abort_unless($request->user()?->can('view', $budget) === true, 403);

        return view('admin.finance.budgets.show', [
            'budget' => $budget->load(['project.agency', 'fiscalYear', 'fundingSource', 'category', 'type', 'status', 'revisions.approver', 'transactions.type', 'transactions.user']),
        ]);
    }

    public function edit(Request $request, Budget $budget): View
    {
        abort_unless($request->user()?->can('update', $budget) === true, 403);

        return view('admin.finance.budgets.form', array_merge($this->lookupData(), [
            'budget' => $budget,
        ]));
    }

    public function update(BudgetRequest $request, Budget $budget, BudgetLifecycleService $service): RedirectResponse
    {
        $service->update($budget, $request->validated());

        return redirect()->route('admin.finance.budgets.show', $budget)->with('status', 'budget-updated');
    }

    public function archive(Request $request, Budget $budget, BudgetLifecycleService $service): RedirectResponse
    {
        abort_unless($request->user()?->can('archive', $budget) === true, 403);
        $service->archive($budget);

        return back()->with('status', 'budget-archived');
    }

    public function restore(Request $request, Budget $budget, BudgetLifecycleService $service): RedirectResponse
    {
        abort_unless($request->user()?->can('restore', $budget) === true, 403);
        $service->restore($budget);

        return back()->with('status', 'budget-restored');
    }

    /**
     * @return array<string, mixed>
     */
    private function lookupData(): array
    {
        return [
            'projects' => Project::query()->with('agency')->orderBy('name')->get(),
            'agencies' => Agency::query()->orderBy('name')->get(),
            'fiscalYears' => FiscalYear::query()->orderByDesc('starts_on')->get(),
            'fundingSources' => FundingSource::query()->orderBy('name')->get(),
            'categories' => BudgetCategory::query()->orderBy('name')->get(),
            'types' => BudgetType::query()->orderBy('name')->get(),
            'statuses' => BudgetStatus::query()->orderBy('sort_order')->orderBy('name')->get(),
        ];
    }
}
