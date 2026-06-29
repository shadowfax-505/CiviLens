<x-layouts.app :title="$project->name.' - CivicLens'">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ $project->project_code }}</p>
            <h1 class="text-3xl font-bold">{{ $project->name }}</h1>
            <p class="mt-2 max-w-3xl text-slate-600 dark:text-slate-300">{{ $project->description }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.projects.edit', $project) }}" class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700">Edit</a>
            @if ($project->isArchived())
                <form method="POST" action="{{ route('admin.projects.restore', $project) }}">
                    @csrf
                    @method('PATCH')
                    <button class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Restore</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.projects.archive', $project) }}" onsubmit="return confirm('Archive this project?')">
                    @csrf
                    @method('PATCH')
                    <button class="rounded bg-amber-700 px-4 py-2 text-sm font-semibold text-white">Archive</button>
                </form>
            @endif
            <form method="POST" action="{{ route('admin.projects.destroy', $project) }}" onsubmit="return confirm('Delete this project?')">
                @csrf
                @method('DELETE')
                <button class="rounded bg-red-700 px-4 py-2 text-sm font-semibold text-white">Delete</button>
            </form>
        </div>
    </div>

    <section class="mt-8 grid gap-4 md:grid-cols-4">
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm text-slate-500">Status</p>
            <p class="mt-1 text-lg font-semibold">{{ $project->status?->name }}</p>
        </div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm text-slate-500">Progress</p>
            <p class="mt-1 text-lg font-semibold">{{ $project->progress_percentage }}%</p>
        </div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm text-slate-500">Budgets</p>
            <p class="mt-1 text-lg font-semibold">{{ $project->budgets()->count() }}</p>
        </div>
    </section>

    <section class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-xl font-semibold">Project Metadata</h2>
            <dl class="mt-4 grid gap-3 text-sm">
                <div><dt class="text-slate-500">Agency</dt><dd>{{ $project->agency?->name }}</dd></div>
                <div><dt class="text-slate-500">Category</dt><dd>{{ $project->category?->name }}</dd></div>
                <div><dt class="text-slate-500">Priority</dt><dd>{{ $project->priority?->name }}</dd></div>
                <div><dt class="text-slate-500">Funding</dt><dd>{{ $project->fundingSource?->name }}</dd></div>
                <div><dt class="text-slate-500">Fiscal Year</dt><dd>{{ $project->fiscalYear?->name }}</dd></div>
                <div><dt class="text-slate-500">Location</dt><dd>{{ collect([$project->ward?->name, $project->union?->name, $project->upazila?->name, $project->district?->name, $project->division?->name, $project->country?->name])->filter()->join(', ') ?: 'Not assigned' }}</dd></div>
                <div><dt class="text-slate-500">Dates</dt><dd>{{ $project->planned_start_date?->format('Y-m-d') }} -> {{ $project->planned_end_date?->format('Y-m-d') }}</dd></div>
            </dl>
        </div>
        <div class="rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-xl font-semibold">Activity Timeline</h2>
            <ol class="mt-4 space-y-3">
                @forelse ($project->activities as $activity)
                    <li class="rounded border px-3 py-2 text-sm dark:border-slate-800">
                        <div class="font-semibold">{{ str($activity->event)->headline() }}</div>
                        <div class="text-slate-500">{{ $activity->created_at->format('Y-m-d H:i') }} by {{ $activity->actor?->name ?? 'System' }}</div>
                    </li>
                @empty
                    <li class="text-sm text-slate-500">No lifecycle activity has been recorded yet.</li>
                @endforelse
            </ol>
        </div>
    </section>
</x-layouts.app>
