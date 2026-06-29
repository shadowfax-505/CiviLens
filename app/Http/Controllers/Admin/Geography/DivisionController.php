<?php

namespace App\Http\Controllers\Admin\Geography;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Geography\StoreDivisionRequest;
use App\Http\Requests\Admin\Geography\UpdateDivisionRequest;
use App\Models\Country;
use App\Models\Division;
use App\Services\Geography\GeographyListingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DivisionController extends Controller
{
    public function index(Request $request, GeographyListingService $service): View
    {
        abort_unless($request->user()?->can('viewAny', Division::class) === true, 403);

        return view('admin.geography.index', [
            'title' => 'Divisions / States',
            'resourceName' => 'divisions',
            'records' => $service->paginate($request, Division::class, ['name', 'code'], ['country_id' => 'country_id'], ['name', 'code', 'created_at'], ['country']),
            'columns' => ['name' => 'Name', 'code' => 'Code'],
            'createRoute' => route('admin.geography.divisions.create'),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('create', Division::class) === true, 403);

        return view('admin.geography.division-form', ['division' => new Division, 'countries' => Country::query()->orderBy('name')->get()]);
    }

    public function store(StoreDivisionRequest $request): RedirectResponse
    {
        Division::query()->create($request->validated());

        return redirect()->route('admin.geography.divisions.index')->with('status', 'division-created');
    }

    public function edit(Request $request, Division $division): View
    {
        abort_unless($request->user()?->can('update', $division) === true, 403);

        return view('admin.geography.division-form', ['division' => $division, 'countries' => Country::query()->orderBy('name')->get()]);
    }

    public function update(UpdateDivisionRequest $request, Division $division): RedirectResponse
    {
        $division->update($request->validated());

        return redirect()->route('admin.geography.divisions.index')->with('status', 'division-updated');
    }

    public function destroy(Request $request, Division $division): RedirectResponse
    {
        abort_unless($request->user()?->can('delete', $division) === true, 403);
        $division->delete();

        return back()->with('status', 'division-deleted');
    }
}
