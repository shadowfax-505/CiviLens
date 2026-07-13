<?php

namespace App\Http\Controllers\Admin\Geography;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Geography\StoreCountryRequest;
use App\Http\Requests\Admin\Geography\UpdateCountryRequest;
use App\Models\Country;
use App\Models\District;
use App\Models\Division;
use App\Models\Project;
use App\Models\Upazila;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CountryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('viewAny', Country::class) === true, 403);

        $sort = in_array($request->query('sort'), ['name', 'iso2', 'iso3', 'created_at'], true) ? $request->query('sort') : 'name';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        $records = Country::query()
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('iso2', 'like', "%{$search}%")
                        ->orWhere('iso3', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('division_id'), fn ($query) => $query->whereHas('divisions', fn ($query) => $query->whereKey($request->integer('division_id'))))
            ->when($request->filled('district_id'), fn ($query) => $query->whereHas('divisions.districts', fn ($query) => $query->whereKey($request->integer('district_id'))))
            ->when($request->filled('upazila_id'), fn ($query) => $query->whereHas('divisions.districts.upazilas', fn ($query) => $query->whereKey($request->integer('upazila_id'))))
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return view('admin.geography.index', [
            'title' => 'Countries',
            'resourceName' => 'countries',
            'records' => $records,
            'columns' => ['name' => 'Name', 'iso2' => 'ISO2', 'iso3' => 'ISO3'],
            'sortOptions' => ['name' => 'Name', 'iso2' => 'ISO2', 'iso3' => 'ISO3', 'created_at' => 'Created'],
            'filterOptions' => [
                ['name' => 'division_id', 'label' => 'All divisions / states', 'options' => Division::query()->orderBy('name')->get(['id', 'name'])],
                ['name' => 'district_id', 'label' => 'All districts', 'options' => District::query()->orderBy('name')->get(['id', 'name'])],
                ['name' => 'upazila_id', 'label' => 'All cities / upazilas', 'options' => Upazila::query()->orderBy('name')->get(['id', 'name'])],
            ],
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        abort_unless($request->user()?->can('create', Country::class) === true, 403);

        return redirect()->route('admin.projects.map');
    }

    public function store(StoreCountryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $projectIds = $data['project_ids'] ?? [];
        unset($data['project_ids']);

        $country = Country::query()->create($data);
        $this->syncProjects($country, $projectIds);

        return redirect()->route('admin.geography.countries.index')->with('status', 'country-created');
    }

    public function edit(Request $request, Country $country): View
    {
        abort_unless($request->user()?->can('update', $country) === true, 403);

        return view('admin.geography.country-form', [
            'country' => $country,
            'projects' => Project::query()->orderBy('name')->get(),
            'selectedProjectIds' => Project::query()->where('country_id', $country->id)->pluck('id')->all(),
        ]);
    }

    public function update(UpdateCountryRequest $request, Country $country): RedirectResponse
    {
        $data = $request->validated();
        $projectIds = $data['project_ids'] ?? [];
        unset($data['project_ids']);

        $country->update($data);
        $this->syncProjects($country, $projectIds);

        return redirect()->route('admin.geography.countries.index')->with('status', 'country-updated');
    }

    public function destroy(Request $request, Country $country): RedirectResponse
    {
        abort_unless($request->user()?->can('delete', $country) === true, 403);

        $country->delete();

        return back()->with('status', 'country-deleted');
    }

    /**
     * @param  array<int, int>|null  $projectIds
     */
    private function syncProjects(Country $country, ?array $projectIds): void
    {
        if ($projectIds === null) {
            return;
        }

        Project::query()->where('country_id', $country->id)->whereNotIn('id', $projectIds)->update(['country_id' => null]);
        Project::query()->whereKey($projectIds)->update(['country_id' => $country->id]);
    }
}
