<x-layouts.app :title="$project->name">
    <section class="space-y-8">
        <div>
            <p class="text-sm font-semibold uppercase text-emerald-700">{{ $project->project_code }}</p>
            <h1 class="mt-2 text-3xl font-bold">{{ $project->name }}</h1>
            <p class="mt-2 text-slate-600 dark:text-slate-300">{{ $project->description }}</p>
            <dl class="mt-4 grid gap-3 md:grid-cols-4">
                <div><dt class="text-xs text-slate-500">Agency</dt><dd>{{ $project->agency?->name }}</dd></div>
                <div><dt class="text-xs text-slate-500">Status</dt><dd>{{ $project->status?->name }}</dd></div>
                <div><dt class="text-xs text-slate-500">Progress</dt><dd>{{ $project->progress_percentage }}%</dd></div>
                <div><dt class="text-xs text-slate-500">Planned End</dt><dd>{{ $project->planned_end_date?->toFormattedDateString() }}</dd></div>
            </dl>
        </div>
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
