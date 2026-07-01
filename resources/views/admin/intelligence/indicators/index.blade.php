<x-layouts.app title="Intelligence Indicators">
    <section class="space-y-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-amber-600">Review Queue</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-950 dark:text-white">Intelligence Indicators</h1>
            </div>
            <a href="{{ route('admin.intelligence.index') }}" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:border-amber-300 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200">Dashboard</a>
        </div>

        <form method="GET" action="{{ route('admin.intelligence.indicators.index') }}" class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 md:grid-cols-5">
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200 md:col-span-2">
                Search
                <input name="q" value="{{ $filters['q'] ?? '' }}" class="mt-1 w-full rounded-lg border-slate-300 bg-white text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
            </label>
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                Severity
                <select name="severity" class="mt-1 w-full rounded-lg border-slate-300 bg-white text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">All</option>
                    @foreach (['info', 'warning', 'critical'] as $severity)
                        <option value="{{ $severity }}" @selected(($filters['severity'] ?? '') === $severity)>{{ str($severity)->headline() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                Status
                <select name="status" class="mt-1 w-full rounded-lg border-slate-300 bg-white text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">All</option>
                    @foreach (['pending', 'in_review', 'accepted', 'dismissed', 'needs_more_evidence'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->headline() }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex items-end">
                <button class="w-full rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 dark:bg-white dark:text-slate-950">Apply</button>
            </div>
        </form>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60">
                    <tr>
                        <th class="px-5 py-3">Signal</th>
                        <th class="px-5 py-3">Severity</th>
                        <th class="px-5 py-3">Confidence</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Detected</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($indicators as $indicator)
                        <tr>
                            <td class="px-5 py-4">
                                <a href="{{ route('admin.intelligence.indicators.show', $indicator) }}" class="font-semibold text-slate-950 hover:text-amber-700 dark:text-white">{{ $indicator->title }}</a>
                                <p class="mt-1 text-xs text-slate-500">{{ $indicator->description }}</p>
                            </td>
                            <td class="px-5 py-4">{{ str($indicator->severity)->headline() }}</td>
                            <td class="px-5 py-4">{{ $indicator->confidence_score }}%</td>
                            <td class="px-5 py-4">{{ str($indicator->status)->headline() }}</td>
                            <td class="px-5 py-4">{{ $indicator->detected_at?->toFormattedDateString() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-slate-500">No indicators match the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                {{ $indicators->links() }}
            </div>
        </div>
    </section>
</x-layouts.app>
