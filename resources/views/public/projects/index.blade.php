<x-layouts.app title="Public Projects">
    <section class="space-y-6">
        <h1 class="text-3xl font-bold">Public Projects</h1>
        <form method="GET" class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            <input name="q" value="{{ $filters['q'] ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950" placeholder="Search projects">
            <select name="agency_id"><option value="">All agencies</option>@foreach ($agencies as $agency)<option value="{{ $agency->id }}" @selected(($filters['agency_id'] ?? null) == $agency->id)>{{ $agency->name }}</option>@endforeach</select>
            <select name="status_id"><option value="">All statuses</option>@foreach ($statuses as $status)<option value="{{ $status->id }}" @selected(($filters['status_id'] ?? null) == $status->id)>{{ $status->name }}</option>@endforeach</select>
            <select name="district_id"><option value="">All districts</option>@foreach ($districts as $district)<option value="{{ $district->id }}" @selected(($filters['district_id'] ?? null) == $district->id)>{{ $district->name }}</option>@endforeach</select>
            <button class="rounded-lg bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950">Search</button>
        </form>
        <x-project-portfolio-map :endpoint="route('public.projects.map-data')" label="Published project locations" />
        <div class="grid gap-4 md:grid-cols-2">
            @forelse ($projects as $project)
                <article class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <a class="text-lg font-semibold" href="{{ route('public.projects.show', $project) }}">{{ $project->name }}</a>
                    <p class="mt-1 text-sm text-slate-500">{{ $project->project_code }} · {{ $project->agency?->name }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ collect([$project->ward?->name, $project->union?->name, $project->upazila?->name, $project->district?->name, $project->division?->name, $project->country?->name])->filter()->join(', ') ?: 'Location not listed' }}</p>
                    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">{{ str($project->description)->limit(180) }}</p>
                </article>
            @empty
                <p class="text-slate-500">No public projects match the current filters.</p>
            @endforelse
        </div>
        {{ $projects->links() }}
    </section>
</x-layouts.app>
