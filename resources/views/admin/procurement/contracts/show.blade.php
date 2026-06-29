<x-layouts.app :title="'Contract Detail - CivicLens'">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Contract Detail</p>
            <h1 class="text-3xl font-bold">{{ $contract->title }}</h1>
            <p class="mt-2 text-slate-600 dark:text-slate-300">{{ $contract->contract_number }} | {{ $contract->bidSubmission?->bidderOrganization?->name }}</p>
        </div>
        <a href="{{ route('admin.procurement.tenders.show', $contract->award->tender) }}" class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700">Back to tender</a>
    </div>

    <section class="mt-8 grid gap-4 md:grid-cols-4">
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Status</p><p class="text-xl font-bold">{{ $contract->status }}</p></div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Project</p><p class="text-xl font-bold">{{ $contract->project?->name }}</p></div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Budget Ref</p><p class="text-xl font-bold">{{ $contract->budget?->id }}</p></div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">End Date</p><p class="text-xl font-bold">{{ $contract->end_date?->format('Y-m-d') ?: 'Open' }}</p></div>
    </section>

    <section class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-xl font-semibold">Contract Milestones</h2>
            <ol class="mt-4 space-y-2">
                @forelse ($contract->milestones as $milestone)
                    <li class="rounded border px-3 py-2 text-sm dark:border-slate-800">{{ $milestone->title }} - {{ $milestone->status }}</li>
                @empty
                    <li class="text-sm text-slate-500">No milestones recorded.</li>
                @endforelse
            </ol>
        </div>
        <div class="rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-xl font-semibold">Procurement Timeline</h2>
            <ol class="mt-4 space-y-2">
                @forelse ($contract->activities as $activity)
                    <li class="rounded border px-3 py-2 text-sm dark:border-slate-800">{{ $activity->event }} - {{ $activity->description }}</li>
                @empty
                    <li class="text-sm text-slate-500">No contract activity recorded.</li>
                @endforelse
            </ol>
        </div>
    </section>
</x-layouts.app>
