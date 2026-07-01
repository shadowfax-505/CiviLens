<?php

namespace App\Http\Controllers\Admin\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Procurement\TenderRequest;
use App\Models\Agency;
use App\Models\BidderOrganization;
use App\Models\Budget;
use App\Models\FiscalYear;
use App\Models\ProcurementMethod;
use App\Models\Project;
use App\Models\Tender;
use App\Models\TenderCategory;
use App\Models\TenderStatus;
use App\Services\Procurement\ProcurementDashboardService;
use App\Services\Procurement\ProcurementLifecycleService;
use App\Services\Procurement\TenderListingService;
use App\Support\Http\AuthenticatedUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenderController extends Controller
{
    public function index(Request $request, TenderListingService $listing, ProcurementDashboardService $dashboard): View
    {
        abort_unless($request->user()?->can('viewAny', Tender::class) === true, 403);

        return view('admin.procurement.tenders.index', array_merge($this->lookupData(), [
            'tenders' => $listing->paginate($request),
            'summary' => $dashboard->summary(),
            'archived' => false,
        ]));
    }

    public function archived(Request $request, TenderListingService $listing, ProcurementDashboardService $dashboard): View
    {
        abort_unless($request->user()?->can('viewAny', Tender::class) === true, 403);

        return view('admin.procurement.tenders.index', array_merge($this->lookupData(), [
            'tenders' => $listing->paginate($request, archived: true),
            'summary' => $dashboard->summary(),
            'archived' => true,
        ]));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('create', Tender::class) === true, 403);

        return view('admin.procurement.tenders.form', array_merge($this->lookupData(), [
            'tender' => new Tender,
        ]));
    }

    public function store(TenderRequest $request, ProcurementLifecycleService $service): RedirectResponse
    {
        $tender = $service->createTender($request->validated(), AuthenticatedUser::from($request));

        return redirect()->route('admin.procurement.tenders.show', $tender)->with('status', 'tender-created');
    }

    public function show(Request $request, Tender $tender): View
    {
        abort_unless($request->user()?->can('view', $tender) === true, 403);

        return view('admin.procurement.tenders.show', [
            'tender' => $tender->load([
                'project',
                'budget.fiscalYear',
                'agency',
                'method',
                'category',
                'status',
                'bidSubmissions.bidderOrganization',
                'bidSubmissions.scores.criterion',
                'bidSubmissions.evaluationSummary',
                'bidSubmissions.openingRecord',
                'evaluationCriteria',
                'awards.bidSubmission.bidderOrganization',
                'awards.contract',
                'awards.approvals',
                'activities.actor',
            ]),
            'bidders' => BidderOrganization::query()->orderBy('name')->get(),
        ]);
    }

    public function edit(Request $request, Tender $tender): View
    {
        abort_unless($request->user()?->can('update', $tender) === true, 403);

        return view('admin.procurement.tenders.form', array_merge($this->lookupData(), [
            'tender' => $tender,
        ]));
    }

    public function update(TenderRequest $request, Tender $tender, ProcurementLifecycleService $service): RedirectResponse
    {
        $service->updateTender($tender, $request->validated(), AuthenticatedUser::from($request));

        return redirect()->route('admin.procurement.tenders.show', $tender)->with('status', 'tender-updated');
    }

    public function publish(Request $request, Tender $tender, ProcurementLifecycleService $service): RedirectResponse
    {
        abort_unless($request->user()?->can('update', $tender) === true, 403);
        $service->publish($tender, AuthenticatedUser::from($request));

        return back()->with('status', 'tender-published');
    }

    public function close(Request $request, Tender $tender, ProcurementLifecycleService $service): RedirectResponse
    {
        abort_unless($request->user()?->can('update', $tender) === true, 403);
        $service->close($tender, AuthenticatedUser::from($request));

        return back()->with('status', 'tender-closed');
    }

    public function archive(Request $request, Tender $tender, ProcurementLifecycleService $service): RedirectResponse
    {
        abort_unless($request->user()?->can('archive', $tender) === true, 403);
        $service->archive($tender, AuthenticatedUser::from($request));

        return back()->with('status', 'tender-archived');
    }

    public function restore(Request $request, Tender $tender, ProcurementLifecycleService $service): RedirectResponse
    {
        abort_unless($request->user()?->can('restore', $tender) === true, 403);
        $service->restore($tender, AuthenticatedUser::from($request));

        return back()->with('status', 'tender-restored');
    }

    /**
     * @return array<string, mixed>
     */
    private function lookupData(): array
    {
        return [
            'projects' => Project::query()->orderBy('name')->get(),
            'budgets' => Budget::query()->with(['project', 'fiscalYear'])->orderByDesc('created_at')->get(),
            'agencies' => Agency::query()->orderBy('name')->get(),
            'methods' => ProcurementMethod::query()->orderBy('name')->get(),
            'categories' => TenderCategory::query()->orderBy('name')->get(),
            'statuses' => TenderStatus::query()->orderBy('sort_order')->orderBy('name')->get(),
            'fiscalYears' => FiscalYear::query()->orderByDesc('starts_on')->get(),
        ];
    }
}
