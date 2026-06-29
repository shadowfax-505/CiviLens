<?php

namespace App\Http\Controllers\Admin\Geography;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Geography\StoreDistrictRequest;
use App\Http\Requests\Admin\Geography\UpdateDistrictRequest;
use App\Models\District;
use App\Models\Division;
use App\Services\Geography\GeographyListingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DistrictController extends Controller
{
    public function index(Request $request, GeographyListingService $service): View
    {
        abort_unless($request->user()?->can('viewAny', District::class), 403);

        return view('admin.geography.index', [
            'title' => 'Districts',
            'resourceName' => 'districts',
            'records' => $service->paginate($request, District::class, ['name', 'code'], ['division_id' => 'division_id'], ['name', 'code', 'created_at'], ['division.country']),
            'columns' => ['name' => 'Name', 'code' => 'Code'],
            'createRoute' => route('admin.geography.districts.create'),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('create', District::class), 403);

        return view('admin.geography.district-form', ['district' => new District, 'divisions' => Division::query()->with('country')->orderBy('name')->get()]);
    }

    public function store(StoreDistrictRequest $request): RedirectResponse
    {
        District::query()->create($request->validated());

        return redirect()->route('admin.geography.districts.index')->with('status', 'district-created');
    }

    public function edit(Request $request, District $district): View
    {
        abort_unless($request->user()?->can('update', $district), 403);

        return view('admin.geography.district-form', ['district' => $district, 'divisions' => Division::query()->with('country')->orderBy('name')->get()]);
    }

    public function update(UpdateDistrictRequest $request, District $district): RedirectResponse
    {
        $district->update($request->validated());

        return redirect()->route('admin.geography.districts.index')->with('status', 'district-updated');
    }

    public function destroy(Request $request, District $district): RedirectResponse
    {
        abort_unless($request->user()?->can('delete', $district), 403);
        $district->delete();

        return back()->with('status', 'district-deleted');
    }
}
