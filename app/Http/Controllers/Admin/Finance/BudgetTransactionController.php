<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Finance\BudgetTransactionRequest;
use App\Models\Budget;
use App\Models\BudgetTransaction;
use App\Services\Finance\BudgetLifecycleService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class BudgetTransactionController extends Controller
{
    public function store(BudgetTransactionRequest $request, Budget $budget, BudgetLifecycleService $service): RedirectResponse
    {
        $service->transact($budget, $request->validated(), $request->user());

        return back()->with('status', 'budget-transaction-recorded');
    }

    public function destroy(BudgetTransaction $budgetTransaction): Response
    {
        abort(405, 'Financial transactions are immutable.');
    }
}
