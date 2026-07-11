<?php

namespace App\Http\Controllers\PublicPortal;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationCompanyType;
use App\Models\OrganizationIndustry;
use App\Services\PublicPortal\PublicContractorService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicContractorController extends Controller
{
    public function index(Request $request, PublicContractorService $contractors): View
    {
        return view('public.contractors.index', [
            'contractors' => $contractors->listing($request->only('q', 'organization_company_type_id', 'organization_industry_id')),
            'filters' => $request->only('q', 'organization_company_type_id', 'organization_industry_id'),
            'companyTypes' => OrganizationCompanyType::query()->orderBy('name')->get(),
            'industries' => OrganizationIndustry::query()->orderBy('name')->get(),
        ]);
    }

    public function show(Organization $organization, PublicContractorService $contractors): View
    {
        return view('public.contractors.show', [
            'organization' => $contractors->detail($organization),
        ]);
    }
}
