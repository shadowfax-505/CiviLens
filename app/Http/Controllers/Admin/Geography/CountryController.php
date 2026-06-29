<?php

namespace App\Http\Controllers\Admin\Geography;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Geography\StoreCountryRequest;
use App\Http\Requests\Admin\Geography\UpdateCountryRequest;
use App\Models\Country;
use App\Services\Geography\GeographyListingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CountryController extends Controller
{
    public function index(Request $request, GeographyListingService $service): View
    {
        abort_unless($request->user()?->can('viewAny', Country::class) === true, 403);

        return view('admin.geography.index', [
            'title' => 'Countries',
            'resourceName' => 'countries',
            'records' => $service->paginate($request, Country::class, ['name', 'iso2', 'iso3'], [], ['name', 'iso2', 'created_at']),
            'columns' => ['name' => 'Name', 'iso2' => 'ISO2', 'iso3' => 'ISO3'],
            'createRoute' => route('admin.geography.countries.create'),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('create', Country::class) === true, 403);

        return view('admin.geography.country-form', ['country' => new Country]);
    }

    public function store(StoreCountryRequest $request): RedirectResponse
    {
        Country::query()->create($request->validated());

        return redirect()->route('admin.geography.countries.index')->with('status', 'country-created');
    }

    public function edit(Request $request, Country $country): View
    {
        abort_unless($request->user()?->can('update', $country) === true, 403);

        return view('admin.geography.country-form', ['country' => $country]);
    }

    public function update(UpdateCountryRequest $request, Country $country): RedirectResponse
    {
        $country->update($request->validated());

        return redirect()->route('admin.geography.countries.index')->with('status', 'country-updated');
    }

    public function destroy(Request $request, Country $country): RedirectResponse
    {
        abort_unless($request->user()?->can('delete', $country) === true, 403);

        $country->delete();

        return back()->with('status', 'country-deleted');
    }
}
