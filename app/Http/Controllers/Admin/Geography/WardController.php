<?php

namespace App\Http\Controllers\Admin\Geography;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Geography\StoreWardRequest;
use App\Http\Requests\Admin\Geography\UpdateWardRequest;
use App\Models\AdministrativeUnion;
use App\Models\Ward;
use App\Services\Geography\GeographyListingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WardController extends Controller
{
    public function index(Request $request, GeographyListingService $service): View
    {
        abort_unless($request->user()?->can('viewAny', Ward::class) === true, 403);

        return view('admin.geography.index', [
            'title' => 'Wards',
            'resourceName' => 'wards',
            'records' => $service->paginate($request, Ward::class, ['name', 'code'], ['union_id' => 'union_id'], ['name', 'code', 'created_at'], ['union.upazila']),
            'columns' => ['name' => 'Name', 'code' => 'Code'],
            'createRoute' => route('admin.geography.wards.create'),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('create', Ward::class) === true, 403);

        return view('admin.geography.ward-form', ['ward' => new Ward, 'unions' => AdministrativeUnion::query()->with('upazila')->orderBy('name')->get()]);
    }

    public function store(StoreWardRequest $request): RedirectResponse
    {
        Ward::query()->create($request->validated());

        return redirect()->route('admin.geography.wards.index')->with('status', 'ward-created');
    }

    public function edit(Request $request, Ward $ward): View
    {
        abort_unless($request->user()?->can('update', $ward) === true, 403);

        return view('admin.geography.ward-form', ['ward' => $ward, 'unions' => AdministrativeUnion::query()->with('upazila')->orderBy('name')->get()]);
    }

    public function update(UpdateWardRequest $request, Ward $ward): RedirectResponse
    {
        $ward->update($request->validated());

        return redirect()->route('admin.geography.wards.index')->with('status', 'ward-updated');
    }

    public function destroy(Request $request, Ward $ward): RedirectResponse
    {
        abort_unless($request->user()?->can('delete', $ward) === true, 403);
        $ward->delete();

        return back()->with('status', 'ward-deleted');
    }
}
