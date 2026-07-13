<?php

namespace App\Http\Controllers\Admin\Geography;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Geography\StoreUpazilaRequest;
use App\Http\Requests\Admin\Geography\UpdateUpazilaRequest;
use App\Models\District;
use App\Models\Upazila;
use App\Services\Geography\GeographyListingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UpazilaController extends Controller
{
    public function index(Request $request, GeographyListingService $service): View
    {
        abort_unless($request->user()?->can('viewAny', Upazila::class) === true, 403);

        return view('admin.geography.index', [
            'title' => 'Upazilas / Cities',
            'resourceName' => 'upazilas',
            'records' => $service->paginate($request, Upazila::class, ['name', 'code'], ['district_id' => 'district_id'], ['name', 'code', 'created_at'], ['district.division']),
            'columns' => ['name' => 'Name', 'code' => 'Code'],
            'createRoute' => route('admin.geography.upazilas.create'),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('create', Upazila::class) === true, 403);

        return view('admin.geography.upazila-form', ['upazila' => new Upazila, 'districts' => District::query()->with('division')->orderBy('name')->get()]);
    }

    public function store(StoreUpazilaRequest $request): RedirectResponse
    {
        Upazila::query()->create($request->validated());

        return redirect()->route('admin.geography.upazilas.index')->with('status', 'upazila-created');
    }

    public function edit(Request $request, Upazila $upazila): View
    {
        abort_unless($request->user()?->can('update', $upazila) === true, 403);

        return view('admin.geography.upazila-form', ['upazila' => $upazila, 'districts' => District::query()->with('division')->orderBy('name')->get()]);
    }

    public function update(UpdateUpazilaRequest $request, Upazila $upazila): RedirectResponse
    {
        $upazila->update($request->validated());

        return redirect()->route('admin.geography.upazilas.index')->with('status', 'upazila-updated');
    }

    public function destroy(Request $request, Upazila $upazila): RedirectResponse
    {
        abort_unless($request->user()?->can('delete', $upazila) === true, 403);
        $upazila->delete();

        return back()->with('status', 'upazila-deleted');
    }
}
