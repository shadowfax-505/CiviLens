<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Finance\BudgetRevisionRequest;
use App\Models\Budget;
use App\Services\Finance\BudgetLifecycleService;
use Illuminate\Http\RedirectResponse;

class BudgetRevisionController extends Controller
{
    public function store(BudgetRevisionRequest $request, Budget $budget, BudgetLifecycleService $service): RedirectResponse
    {
        $service->revise($budget, $request->validated());

        return back()->with('status', 'budget-revised');
    }
}
