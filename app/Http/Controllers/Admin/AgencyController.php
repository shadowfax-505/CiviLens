<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AgencyRequest;
use App\Models\Agency;
use App\Models\AgencyType;
use App\Models\Country;
use App\Models\User;
use App\Services\Agencies\AgencyListingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgencyController extends Controller
{
    public function index(Request $request, AgencyListingService $service): View
    {
        abort_unless($request->user()?->can('viewAny', Agency::class), 403);

        return view('admin.agencies.index', [
            'agencies' => $service->paginate($request),
            'agencyTypes' => AgencyType::query()->orderBy('name')->get(),
            'countries' => Country::query()->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('create', Agency::class), 403);

        return view('admin.agencies.form', $this->formData(new Agency));
    }

    public function store(AgencyRequest $request): RedirectResponse
    {
        $agency = Agency::query()->create($request->safe()->except('user_ids'));
        $agency->users()->sync($request->validated('user_ids', []));

        return redirect()->route('admin.agencies.index')->with('status', 'agency-created');
    }

    public function edit(Request $request, Agency $agency): View
    {
        abort_unless($request->user()?->can('update', $agency), 403);

        return view('admin.agencies.form', $this->formData($agency));
    }

    public function update(AgencyRequest $request, Agency $agency): RedirectResponse
    {
        $agency->update($request->safe()->except('user_ids'));
        $agency->users()->sync($request->validated('user_ids', []));

        return redirect()->route('admin.agencies.index')->with('status', 'agency-updated');
    }

    public function destroy(Request $request, Agency $agency): RedirectResponse
    {
        abort_unless($request->user()?->can('delete', $agency), 403);

        $agency->delete();

        return back()->with('status', 'agency-deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Agency $agency): array
    {
        return [
            'agency' => $agency,
            'agencyTypes' => AgencyType::query()->orderBy('name')->get(),
            'parentAgencies' => Agency::query()
                ->when($agency->exists, fn ($query) => $query->whereKeyNot($agency->id))
                ->orderBy('name')
                ->get(),
            'countries' => Country::query()->orderBy('name')->get(),
            'users' => User::query()->orderBy('name')->get(),
        ];
    }
}
