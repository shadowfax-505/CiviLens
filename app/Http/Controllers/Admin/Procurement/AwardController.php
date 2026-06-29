<?php

namespace App\Http\Controllers\Admin\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Procurement\AwardRequest;
use App\Models\Tender;
use App\Services\Procurement\ProcurementLifecycleService;
use App\Support\Http\AuthenticatedUser;
use Illuminate\Http\RedirectResponse;

class AwardController extends Controller
{
    public function store(AwardRequest $request, Tender $tender, ProcurementLifecycleService $service): RedirectResponse
    {
        $service->createAward($tender, $request->validated(), AuthenticatedUser::from($request));

        return back()->with('status', 'award-created');
    }
}
