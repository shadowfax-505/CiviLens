<x-layouts.app title="Project Map - CivicLens">
    <section class="space-y-8">
        <div class="rounded-3xl bg-white p-8 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-emerald-600">Geographic Foundation</p>
                    <h1 class="mt-2 text-3xl font-black">Project Map</h1>
                    <p class="mt-2 max-w-3xl text-slate-600 dark:text-slate-300">Assign geography and map coordinates to existing projects. Country creation is hidden from the UI.</p>
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
            <div class="space-y-4">
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-lg font-black">Projects</h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Choose a project to update its location.</p>
                </div>

                <div class="space-y-3">
                    @forelse ($projects as $project)
                        <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-600 dark:text-emerald-300">{{ $project->project_code }}</p>
                                    <h3 class="mt-1 text-lg font-black">{{ $project->name }}</h3>
                                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ collect([$project->ward?->name, $project->union?->name, $project->upazila?->name, $project->district?->name, $project->division?->name, $project->country?->name])->filter()->join(', ') ?: 'Location not assigned' }}</p>
                                </div>
                                <a class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold dark:border-slate-700" href="{{ route('admin.projects.map', ['project_id' => $project->id]) }}">Select</a>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center dark:border-slate-700 dark:bg-slate-900">
                            <h2 class="text-2xl font-black">No projects found</h2>
                            <p class="mt-2 text-slate-600 dark:text-slate-300">Create a project first to assign a map location.</p>
                        </div>
                    @endforelse
                </div>

                <div>{{ $projects->links() }}</div>
            </div>

            <div class="space-y-4">
                @if ($selectedProject)
                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex flex-col gap-2">
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-600 dark:text-emerald-300">Selected Project</p>
                            <h2 class="text-2xl font-black">{{ $selectedProject->name }}</h2>
                            <p class="text-sm text-slate-600 dark:text-slate-300">{{ $selectedProject->project_code }} · {{ $selectedProject->agency?->name ?? 'No agency' }}</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.projects.map.update', $selectedProject) }}" class="space-y-6 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        @csrf
                        @method('PATCH')

                        @include('admin.projects.partials.location-fields', [
                            'project' => $selectedProject,
                            'countries' => $countries,
                            'divisions' => $divisions,
                            'districts' => $districts,
                            'upazilas' => $upazilas,
                            'unions' => $unions,
                            'wards' => $wards,
                        ])

                        <div class="flex flex-wrap gap-3">
                            <button class="rounded-full bg-emerald-600 px-5 py-3 text-sm font-bold text-white hover:bg-emerald-500">Save Project Location</button>
                            <a class="rounded-full border border-slate-300 px-5 py-3 text-sm font-bold dark:border-slate-700" href="{{ route('admin.projects.show', $selectedProject) }}">Open Project Detail</a>
                        </div>
                    </form>
                @else
                    <div class="rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center dark:border-slate-700 dark:bg-slate-900">
                        <h2 class="text-2xl font-black">Select a project</h2>
                        <p class="mt-2 text-slate-600 dark:text-slate-300">Choose a project from the list to assign its map location.</p>
                    </div>
                @endif
            </div>
        </div>
    </section>
</x-layouts.app>
