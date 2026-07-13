@props([
    'project',
    'countries',
    'divisions',
    'districts',
    'upazilas',
    'unions',
    'wards',
])

@php
    $selectedCountryId = old('country_id', $project->country_id);
    $selectedDivisionId = old('division_id', $project->division_id);
    $selectedDistrictId = old('district_id', $project->district_id);
    $selectedUpazilaId = old('upazila_id', $project->upazila_id);
    $selectedUnionId = old('union_id', $project->union_id);
    $selectedWardId = old('ward_id', $project->ward_id);
@endphp

<section class="md:col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-800 dark:bg-slate-900/50">
    <div class="flex flex-col gap-1">
        <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-600 dark:text-emerald-300">Project Location</p>
        <h2 class="text-lg font-black">Geographical Location</h2>
        <p class="text-sm text-slate-600 dark:text-slate-300">Assign the project to an existing geographic hierarchy and pin its exact map location.</p>
    </div>

    <div class="mt-4 grid gap-4 md:grid-cols-2">
        <label class="text-sm">Country
            <select name="country_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950 dark:border-slate-700 dark:bg-slate-950">
                <option value="">No country</option>
                @foreach ($countries as $country)
                    <option value="{{ $country->id }}" @selected((string) $selectedCountryId === (string) $country->id)>{{ $country->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">Division / State
            <select name="division_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950 dark:border-slate-700 dark:bg-slate-950">
                <option value="">No division</option>
                @foreach ($divisions as $division)
                    <option value="{{ $division->id }}" @selected((string) $selectedDivisionId === (string) $division->id)>{{ $division->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">District
            <select name="district_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950 dark:border-slate-700 dark:bg-slate-950">
                <option value="">No district</option>
                @foreach ($districts as $district)
                    <option value="{{ $district->id }}" @selected((string) $selectedDistrictId === (string) $district->id)>{{ $district->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">Upazila / City
            <select name="upazila_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950 dark:border-slate-700 dark:bg-slate-950">
                <option value="">No upazila</option>
                @foreach ($upazilas as $upazila)
                    <option value="{{ $upazila->id }}" @selected((string) $selectedUpazilaId === (string) $upazila->id)>{{ $upazila->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">Union / Municipality
            <select name="union_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950 dark:border-slate-700 dark:bg-slate-950">
                <option value="">No union</option>
                @foreach ($unions as $union)
                    <option value="{{ $union->id }}" @selected((string) $selectedUnionId === (string) $union->id)>{{ $union->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">Ward
            <select name="ward_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950 dark:border-slate-700 dark:bg-slate-950">
                <option value="">No ward</option>
                @foreach ($wards as $ward)
                    <option value="{{ $ward->id }}" @selected((string) $selectedWardId === (string) $ward->id)>{{ $ward->name }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <div class="mt-6">
        <x-leaflet-coordinate-picker :lat="$project->latitude" :lng="$project->longitude" label="Project map pin" />
    </div>
</section>
