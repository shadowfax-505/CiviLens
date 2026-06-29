<?php

namespace App\Http\Controllers\Admin\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Procurement\EvaluationCriterionRequest;
use App\Http\Requests\Admin\Procurement\EvaluationScoreRequest;
use App\Models\BidSubmission;
use App\Models\Tender;
use App\Services\Procurement\ProcurementLifecycleService;
use App\Support\Http\AuthenticatedUser;
use Illuminate\Http\RedirectResponse;

class EvaluationController extends Controller
{
    public function storeCriterion(EvaluationCriterionRequest $request, Tender $tender, ProcurementLifecycleService $service): RedirectResponse
    {
        $service->createCriterion($tender, $request->validated(), AuthenticatedUser::from($request));

        return back()->with('status', 'criterion-created');
    }

    public function storeScore(EvaluationScoreRequest $request, BidSubmission $bidSubmission, ProcurementLifecycleService $service): RedirectResponse
    {
        $service->createScore($bidSubmission, $request->validated(), AuthenticatedUser::from($request));

        return back()->with('status', 'score-recorded');
    }
}
