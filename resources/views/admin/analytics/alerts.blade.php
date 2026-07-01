<x-layouts.app title="Analytics Alerts">
    <section class="space-y-6">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">Analytics Alerts</h1>
            <p class="mt-2 text-slate-600 dark:text-slate-300">Rule-based warnings generated from normalized CivicLens source data.</p>
        </div>
        <div class="grid gap-4">
            @forelse ($alerts as $alert)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $alert->rule?->category ?? 'analytics' }}</p>
                            <h2 class="mt-1 text-xl font-semibold text-slate-950 dark:text-white">{{ $alert->title }}</h2>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $alert->message }}</p>
                        </div>
                        <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-200">{{ str($alert->severity)->headline() }}</span>
                    </div>
                </article>
            @empty
                <p class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">No analytics alerts have been triggered.</p>
            @endforelse
        </div>
        {{ $alerts->links() }}
    </section>
</x-layouts.app>
