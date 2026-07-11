<x-layouts.app :title="$agency->name">
    @php($mapLocation = collect([$agency, $agency->ward, $agency->union, $agency->upazila, $agency->district, $agency->division, $agency->country])->first(fn ($location) => $location?->latitude !== null && $location?->longitude !== null))
    <section class="space-y-8">
        <div>
            <p class="text-sm font-semibold uppercase text-emerald-700">{{ $agency->type?->name }}</p>
            <h1 class="mt-2 text-3xl font-bold">{{ $agency->name }}</h1>
            <p class="mt-2 text-slate-600 dark:text-slate-300">{{ $agency->description }}</p>
            @if (auth()->user()?->hasRole(config('civiclens.roles.staff')))
                <a class="mt-4 inline-flex rounded-full border border-emerald-300 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-300 dark:hover:bg-emerald-950" href="{{ route('admin.change-requests.create', ['module' => 'agencies', 'subject_type' => App\Models\Agency::class, 'subject_id' => $agency->id, 'subject_label' => $agency->name, 'subject_url' => route('public.agencies.show', $agency)]) }}">Request change</a>
            @endif
        </div>
        @if ($mapLocation)
            <x-leaflet-static-map :label="$agency->name.' location'" :lat="$mapLocation->latitude" :lng="$mapLocation->longitude" :geojson="$mapLocation->geojson ?? null" :markers="$agency->children->filter(fn ($child) => $child->status === 'active' && $child->latitude !== null && $child->longitude !== null)->map(fn ($child) => ['lat' => $child->latitude, 'lng' => $child->longitude, 'label' => $child->name])->values()->all()" />
        @endif
        <div class="grid gap-6 md:grid-cols-2">
            <section class="space-y-3">
                <h2 class="text-xl font-semibold">Public Projects</h2>
                @forelse ($projects as $project)
                    <a class="block rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900" href="{{ route('public.projects.show', $project) }}">{{ $project->name }}</a>
                @empty
                    <p class="text-sm text-slate-500">No public projects are available.</p>
                @endforelse
            </section>
            <section class="space-y-3">
                <h2 class="text-xl font-semibold">Public Procurement</h2>
                @forelse ($tenders as $tender)
                    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">{{ $tender->title }}</div>
                @empty
                    <p class="text-sm text-slate-500">No public procurement records are available.</p>
                @endforelse
            </section>
        </div>
    </section>
</x-layouts.app>
