<?php

namespace App\Http\Controllers\Citizen;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Budget;
use App\Models\FiscalYear;
use App\Models\Project;
use App\Services\Finance\BudgetCalculationService;
use App\Services\Finance\BudgetListingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CitizenBudgetDashboardController extends Controller
{
    public function index(Request $request, BudgetListingService $listing, BudgetCalculationService $calculations): View
    {
        abort_unless($request->user()?->can('viewCitizenDashboard', Budget::class) === true, 403);

        /** @var view-string $view */
        $view = 'citizen.budgets.index';

        return view($view, [
            'budgets' => $listing->paginate($request, publicOnly: true),
            'summary' => $calculations->dashboardSummary(publicOnly: true),
            'fiscalYearSummaries' => $calculations->fiscalYearSummaries(publicOnly: true),
            'projects' => Project::query()->where('is_public', true)->where('is_active', true)->orderBy('name')->get(),
            'agencies' => Agency::query()->where('status', 'active')->orderBy('name')->get(),
            'fiscalYears' => FiscalYear::query()->orderByDesc('starts_on')->get(),
        ]);
    }
}
