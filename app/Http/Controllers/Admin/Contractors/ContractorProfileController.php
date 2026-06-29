<?php

namespace App\Http\Controllers\Admin\Contractors;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Contractors\ContractorProfileRequest;
use App\Models\Organization;
use App\Services\Contractors\ContractorLifecycleService;
use Illuminate\Http\RedirectResponse;

class ContractorProfileController extends Controller
{
    public function store(ContractorProfileRequest $request, Organization $organization, ContractorLifecycleService $service): RedirectResponse
    {
        $service->registerProfile($organization, array_merge($request->validated(), [
            'is_active' => $request->boolean('is_active'),
            'is_suspended' => $request->boolean('is_suspended'),
            'is_blacklisted' => $request->boolean('is_blacklisted'),
            'is_public' => $request->boolean('is_public'),
        ]));

        return redirect()->route('admin.contractors.organizations.show', $organization)->with('status', 'contractor-profile-updated');
    }
}
