<?php

namespace App\Http\Controllers\Admin\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Procurement\ApproveAwardRequest;
use App\Http\Requests\Admin\Procurement\ApproveVariationRequest;
use App\Http\Requests\Admin\Procurement\CloseContractRequest;
use App\Http\Requests\Admin\Procurement\CompleteMilestoneRequest;
use App\Http\Requests\Admin\Procurement\FinalizeEvaluationRequest;
use App\Http\Requests\Admin\Procurement\OpenBidRequest;
use App\Http\Requests\Admin\Procurement\RecordContractPaymentRequest;
use App\Models\Award;
use App\Models\BidSubmission;
use App\Models\Contract;
use App\Models\ContractMilestone;
use App\Models\VariationOrder;
use App\Services\Procurement\ProcurementLifecycleService;
use App\Support\Http\AuthenticatedUser;
use Illuminate\Http\RedirectResponse;

class ProcurementWorkflowController extends Controller
{
    public function openBid(OpenBidRequest $request, BidSubmission $bidSubmission, ProcurementLifecycleService $service): RedirectResponse
    {
        $service->openBid($bidSubmission, AuthenticatedUser::from($request), $request->validated('notes'));

        return back()->with('status', 'bid-opened');
    }

    public function finalizeEvaluation(FinalizeEvaluationRequest $request, BidSubmission $bidSubmission, ProcurementLifecycleService $service): RedirectResponse
    {
        $service->finalizeEvaluation($bidSubmission, AuthenticatedUser::from($request), $request->validated('recommendation'));

        return back()->with('status', 'evaluation-finalized');
    }

    public function approveAward(ApproveAwardRequest $request, Award $award, ProcurementLifecycleService $service): RedirectResponse
    {
        $service->approveAward($award, AuthenticatedUser::from($request), $request->validated('notes'));

        return back()->with('status', 'award-approved');
    }

    public function recordPayment(RecordContractPaymentRequest $request, Contract $contract, ProcurementLifecycleService $service): RedirectResponse
    {
        $service->recordContractPayment($contract, AuthenticatedUser::from($request), $request->validated());

        return back()->with('status', 'contract-payment-recorded');
    }

    public function completeMilestone(CompleteMilestoneRequest $request, ContractMilestone $milestone, ProcurementLifecycleService $service): RedirectResponse
    {
        $validated = $request->validated();
        $service->completeMilestone($milestone, AuthenticatedUser::from($request), (int) $validated['completion_percentage'], $validated['evidence_summary'] ?? null);

        return back()->with('status', 'milestone-completed');
    }

    public function approveVariation(ApproveVariationRequest $request, VariationOrder $variationOrder, ProcurementLifecycleService $service): RedirectResponse
    {
        $service->approveVariation($variationOrder, AuthenticatedUser::from($request), $request->validated());

        return back()->with('status', 'variation-approved');
    }

    public function closeContract(CloseContractRequest $request, Contract $contract, ProcurementLifecycleService $service): RedirectResponse
    {
        $service->closeContract($contract, AuthenticatedUser::from($request), $request->validated('notes'));

        return back()->with('status', 'contract-closed');
    }
}
