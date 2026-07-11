<x-layouts.app :title="$project->name">
    @php($mapLocation = ($project->latitude !== null && $project->longitude !== null) ? $project : collect([$project->ward, $project->union, $project->upazila, $project->district, $project->division, $project->country])->first(fn ($location) => $location?->latitude !== null && $location?->longitude !== null))
    <section class="space-y-8">
        <div class="space-y-4">
            <p class="text-sm font-semibold uppercase text-emerald-700">{{ $project->project_code }}</p>
            <h1 class="mt-2 text-3xl font-bold">{{ $project->name }}</h1>
            <p class="mt-2 text-slate-600 dark:text-slate-300">{{ $project->description }}</p>
            @if (auth()->user()?->hasRole(config('civiclens.roles.staff')))
                <a class="mt-4 inline-flex rounded-full border border-emerald-300 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-300 dark:hover:bg-emerald-950" href="{{ route('admin.change-requests.create', ['module' => 'projects', 'subject_type' => App\Models\Project::class, 'subject_id' => $project->id, 'subject_label' => $project->name, 'subject_url' => route('public.projects.show', $project)]) }}">Request change</a>
            @endif
            <dl class="mt-4 grid gap-3 md:grid-cols-4">
                <div><dt class="text-xs text-slate-500">Agency</dt><dd>{{ $project->agency?->name }}</dd></div>
                <div><dt class="text-xs text-slate-500">Status</dt><dd>{{ $project->status?->name }}</dd></div>
                <div><dt class="text-xs text-slate-500">Progress</dt><dd>{{ $project->progress_percentage }}%</dd></div>
                <div><dt class="text-xs text-slate-500">Planned End</dt><dd>{{ $project->planned_end_date?->toFormattedDateString() }}</dd></div>
                <div class="md:col-span-4"><dt class="text-xs text-slate-500">Geographical Location</dt><dd>{{ collect([$project->ward?->name, $project->union?->name, $project->upazila?->name, $project->district?->name, $project->division?->name, $project->country?->name])->filter()->join(', ') ?: 'Not assigned' }}</dd></div>
            </dl>
        </div>

        @if ($mapLocation || ! empty($project->geojson))
            <x-leaflet-static-map
                :label="$project->name.' location'"
                :lat="$mapLocation?->latitude"
                :lng="$mapLocation?->longitude"
                :geojson="$project->geojson"
            />
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="space-y-3">
                <h2 class="text-xl font-semibold">Budgets</h2>
                @forelse ($budgets as $budget)
                    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">{{ $budget->budget_code }} · {{ number_format((float) $budget->current_allocation, 2) }}</div>
                @empty
                    <p class="text-sm text-slate-500">No public budget summary is available.</p>
                @endforelse
            </section>
            <section class="space-y-3">
                <h2 class="text-xl font-semibold">Procurement</h2>
                @forelse ($tenders as $tender)
                    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">{{ $tender->title }}</div>
                @empty
                    <p class="text-sm text-slate-500">No public procurement records are available.</p>
                @endforelse
            </section>
            <section class="space-y-3">
                <h2 class="text-xl font-semibold">Documents</h2>
                @forelse ($documents as $document)
                    <a class="block rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900" href="{{ route('public.documents.download', $document) }}">{{ $document->title }}</a>
                @empty
                    <p class="text-sm text-slate-500">No public documents are available.</p>
                @endforelse
            </section>
        </div>
    </section>
</x-layouts.app>
