<x-layouts.app title="Intelligence Rule Management">
    <section class="space-y-8">
        <div class="rounded-3xl bg-slate-950 p-8 text-white shadow-sm dark:bg-slate-900">
            <p class="text-sm font-semibold uppercase tracking-[0.24em] text-amber-300">Rule Management Console</p>
            <h1 class="mt-3 text-4xl font-bold tracking-tight">Deterministic Integrity Rules</h1>
            <p class="mt-3 max-w-3xl text-slate-300">Tune thresholds, weights, priorities, and documentation for explainable Civic Integrity Engine rules. Changes are audited.</p>
        </div>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/70">
                        <tr>
                            <th class="px-4 py-3">Rule</th>
                            <th class="px-4 py-3">Module</th>
                            <th class="px-4 py-3">Priority</th>
                            <th class="px-4 py-3">Weight</th>
                            <th class="px-4 py-3">Last Execution</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($rules as $rule)
                            <tr>
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.intelligence.rules.show', $rule) }}" class="font-semibold text-slate-950 hover:text-amber-700 dark:text-white">{{ $rule->name }}</a>
                                    <p class="text-xs text-slate-500">{{ $rule->slug }}</p>
                                </td>
                                <td class="px-4 py-3">{{ str($rule->module)->headline() }}</td>
                                <td class="px-4 py-3">{{ number_format($rule->priority ?? 100) }}</td>
                                <td class="px-4 py-3">{{ number_format($rule->weight ?? 50) }}%</td>
                                <td class="px-4 py-3">{{ $rule->last_executed_at?->diffForHumans() ?? 'Not run' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('admin.intelligence.rules.show', $rule) }}" class="rounded-lg bg-slate-950 px-3 py-2 text-xs font-semibold text-white dark:bg-white dark:text-slate-950">Manage</a>
                                        <form method="POST" action="{{ route('admin.intelligence.rules.dry-run', $rule) }}">
                                            @csrf
                                            <button class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:border-amber-300 dark:border-slate-700 dark:text-slate-200">Dry Run</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">No intelligence rules are configured.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{ $rules->links() }}
    </section>
</x-layouts.app>
