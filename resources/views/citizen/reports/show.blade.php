<x-layouts.app :title="$report->title">
    <section class="space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase text-emerald-700">Tracking {{ $report->public_uuid }}</p>
            <h1 class="mt-2 text-3xl font-bold">{{ $report->title }}</h1>
            <p class="mt-2 text-slate-600 dark:text-slate-300">{{ $report->description }}</p>
        </div>
        <dl class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><dt class="text-xs text-slate-500">Category</dt><dd>{{ $report->category?->name }}</dd></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><dt class="text-xs text-slate-500">Status</dt><dd>{{ $report->status?->name }}</dd></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><dt class="text-xs text-slate-500">Submitted</dt><dd>{{ $report->submitted_at?->toFormattedDateString() }}</dd></div>
        </dl>
    </section>
</x-layouts.app>
