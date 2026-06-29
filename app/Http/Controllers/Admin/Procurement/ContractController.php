<?php

namespace App\Http\Controllers\Admin\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Procurement\ContractRequest;
use App\Models\Award;
use App\Models\Contract;
use App\Models\Tender;
use App\Services\Procurement\ProcurementLifecycleService;
use App\Support\Http\AuthenticatedUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContractController extends Controller
{
    public function store(ContractRequest $request, Award $award, ProcurementLifecycleService $service): RedirectResponse
    {
        $contract = $service->createContract($award, $request->validated(), AuthenticatedUser::from($request));

        return redirect()->route('admin.procurement.contracts.show', $contract)->with('status', 'contract-created');
    }

    public function show(Request $request, Contract $contract): View
    {
        $award = $contract->award;
        $tender = $award instanceof Award ? $award->tender : null;

        abort_unless($tender instanceof Tender && $request->user()?->can('view', $tender) === true, 403);

        return view('admin.procurement.contracts.show', [
            'contract' => $contract->load([
                'award.tender.project',
                'award.tender.budget.fiscalYear',
                'bidSubmission.bidderOrganization',
                'project',
                'budget',
                'milestones',
                'variationOrders',
                'extensions',
                'liquidatedDamages',
                'completionCertificates',
                'activities.actor',
            ]),
        ]);
    }
}
