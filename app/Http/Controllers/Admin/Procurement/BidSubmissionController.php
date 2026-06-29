<?php

namespace App\Http\Controllers\Admin\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Procurement\BidSubmissionRequest;
use App\Models\Tender;
use App\Services\Procurement\ProcurementLifecycleService;
use App\Support\Http\AuthenticatedUser;
use Illuminate\Http\RedirectResponse;

class BidSubmissionController extends Controller
{
    public function store(BidSubmissionRequest $request, Tender $tender, ProcurementLifecycleService $service): RedirectResponse
    {
        $service->createBid($tender, $request->validated(), AuthenticatedUser::from($request));

        return back()->with('status', 'bid-submitted');
    }
}
