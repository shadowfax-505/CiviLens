<?php

namespace App\Http\Controllers\Admin\Contractors;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Contractors\OrganizationRequest;
use App\Models\ContractorCategory;
use App\Models\ContractorClassification;
use App\Models\ContractorProfile;
use App\Models\ContractorRegistrationStatus;
use App\Models\ContractorRiskLevel;
use App\Models\Country;
use App\Models\Organization;
use App\Models\OrganizationCompanyType;
use App\Models\OrganizationIndustry;
use App\Services\Contractors\ContractorDashboardService;
use App\Services\Contractors\ContractorLifecycleService;
use App\Services\Contractors\ContractorListingService;
use App\Services\Contractors\ContractorScoreService;
use App\Support\Http\AuthenticatedUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function index(Request $request, ContractorListingService $listing, ContractorDashboardService $dashboard): View
    {
        abort_unless($request->user()?->can('viewAny', Organization::class) === true, 403);

        return view('admin.contractors.organizations.index', array_merge($this->lookupData(), [
            'organizations' => $listing->paginate($request),
            'summary' => $dashboard->summary(),
            'archived' => false,
        ]));
    }

    public function archived(Request $request, ContractorListingService $listing, ContractorDashboardService $dashboard): View
    {
        abort_unless($request->user()?->can('viewAny', Organization::class) === true, 403);

        return view('admin.contractors.organizations.index', array_merge($this->lookupData(), [
            'organizations' => $listing->paginate($request, archived: true),
            'summary' => $dashboard->summary(),
            'archived' => true,
        ]));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('create', Organization::class) === true, 403);

        return view('admin.contractors.organizations.form', array_merge($this->lookupData(), [
            'organization' => new Organization,
        ]));
    }

    public function store(OrganizationRequest $request, ContractorLifecycleService $service): RedirectResponse
    {
        $organization = $service->createOrganization($request->validated(), AuthenticatedUser::from($request));

        return redirect()->route('admin.contractors.organizations.show', $organization)->with('status', 'organization-created');
    }

    public function show(Request $request, Organization $organization, ContractorScoreService $scores): View
    {
        abort_unless($request->user()?->can('view', $organization) === true, 403);

        $organization->load([
            'companyType',
            'industry',
            'country',
            'branches',
            'profile.category',
            'profile.classification',
            'profile.registrationStatus',
            'profile.riskLevel',
            'profile.complianceRecords.type',
            'profile.complianceRecords.status',
            'profile.performanceSnapshots.project',
            'profile.activities',
        ]);

        return view('admin.contractors.organizations.show', array_merge($this->lookupData(), [
            'organization' => $organization,
            'scorecard' => $organization->profile instanceof ContractorProfile ? $scores->calculate($organization->profile) : null,
        ]));
    }

    public function edit(Request $request, Organization $organization): View
    {
        abort_unless($request->user()?->can('update', $organization) === true, 403);

        return view('admin.contractors.organizations.form', array_merge($this->lookupData(), [
            'organization' => $organization,
        ]));
    }

    public function update(OrganizationRequest $request, Organization $organization, ContractorLifecycleService $service): RedirectResponse
    {
        $service->updateOrganization($organization, $request->validated(), AuthenticatedUser::from($request));

        return redirect()->route('admin.contractors.organizations.show', $organization)->with('status', 'organization-updated');
    }

    public function archive(Request $request, Organization $organization, ContractorLifecycleService $service): RedirectResponse
    {
        abort_unless($request->user()?->can('archive', $organization) === true, 403);

        $service->archiveOrganization($organization);

        return back()->with('status', 'organization-archived');
    }

    public function restore(Request $request, Organization $organization, ContractorLifecycleService $service): RedirectResponse
    {
        abort_unless($request->user()?->can('restore', $organization) === true, 403);

        $service->restoreOrganization($organization);

        return back()->with('status', 'organization-restored');
    }

    /**
     * @return array<string, mixed>
     */
    private function lookupData(): array
    {
        return [
            'companyTypes' => OrganizationCompanyType::query()->orderBy('name')->get(),
            'industries' => OrganizationIndustry::query()->orderBy('name')->get(),
            'countries' => Country::query()->orderBy('name')->get(),
            'categories' => ContractorCategory::query()->orderBy('name')->get(),
            'classifications' => ContractorClassification::query()->orderBy('name')->get(),
            'registrationStatuses' => ContractorRegistrationStatus::query()->orderBy('name')->get(),
            'riskLevels' => ContractorRiskLevel::query()->orderBy('sort_order')->orderBy('name')->get(),
        ];
    }
}
