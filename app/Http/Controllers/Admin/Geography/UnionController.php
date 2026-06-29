<?php

namespace App\Http\Controllers\Admin\Geography;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Geography\StoreUnionRequest;
use App\Http\Requests\Admin\Geography\UpdateUnionRequest;
use App\Models\AdministrativeUnion;
use App\Models\Upazila;
use App\Services\Geography\GeographyListingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnionController extends Controller
{
    public function index(Request $request, GeographyListingService $service): View
    {
        abort_unless($request->user()?->can('viewAny', AdministrativeUnion::class) === true, 403);

        return view('admin.geography.index', [
            'title' => 'Unions / Municipalities',
            'resourceName' => 'unions',
            'records' => $service->paginate($request, AdministrativeUnion::class, ['name', 'type', 'code'], ['upazila_id' => 'upazila_id', 'type' => 'type'], ['name', 'type', 'code', 'created_at'], ['upazila.district']),
            'columns' => ['name' => 'Name', 'type' => 'Type', 'code' => 'Code'],
            'createRoute' => route('admin.geography.unions.create'),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('create', AdministrativeUnion::class) === true, 403);

        return view('admin.geography.union-form', ['union' => new AdministrativeUnion, 'upazilas' => Upazila::query()->with('district')->orderBy('name')->get()]);
    }

    public function store(StoreUnionRequest $request): RedirectResponse
    {
        AdministrativeUnion::query()->create($request->validated());

        return redirect()->route('admin.geography.unions.index')->with('status', 'union-created');
    }

    public function edit(Request $request, AdministrativeUnion $union): View
    {
        abort_unless($request->user()?->can('update', $union) === true, 403);

        return view('admin.geography.union-form', ['union' => $union, 'upazilas' => Upazila::query()->with('district')->orderBy('name')->get()]);
    }

    public function update(UpdateUnionRequest $request, AdministrativeUnion $union): RedirectResponse
    {
        $union->update($request->validated());

        return redirect()->route('admin.geography.unions.index')->with('status', 'union-updated');
    }

    public function destroy(Request $request, AdministrativeUnion $union): RedirectResponse
    {
        abort_unless($request->user()?->can('delete', $union) === true, 403);
        $union->delete();

        return back()->with('status', 'union-deleted');
    }
}
