<x-layouts.app title="CivicLens Dashboard">
    @php($summary = $summary ?? ['cards' => [], 'risk_summary' => [], 'system_health' => ['status' => 'unknown'], 'recent_integrity_runs' => collect(), 'recent_alerts' => collect(), 'top_agencies' => collect(), 'top_contractors' => collect(), 'charts' => []])

    <section class="space-y-8">
        <div class="rounded-3xl bg-slate-950 p-8 text-white shadow-sm dark:bg-slate-900">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.24em] text-cyan-300">Executive Command Center</p>
                    <h1 class="mt-3 text-4xl font-bold tracking-tight">CivicLens Dashboard</h1>
                    <p class="mt-3 max-w-3xl text-slate-300">Welcome to the CivicLens v1 identity foundation. Monitor delivery, finance, procurement, documents, citizen engagement, and deterministic integrity signals from one production-ready workspace.</p>
                </div>
                <div class="rounded-2xl bg-white/10 px-4 py-3 text-sm">
                    <p class="font-semibold">System Health</p>
                    <p class="mt-1 text-cyan-100">{{ str($summary['system_health']['status'] ?? 'unknown')->upper() }}</p>
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($summary['cards'] as $card)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
                    <p class="mt-3 text-3xl font-bold text-slate-950 dark:text-white">{{ $card['value'] }}</p>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $card['detail'] }}</p>
                </article>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 xl:col-span-2">
                <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Risk Summary</h2>
                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    @foreach (['critical' => 'Critical', 'warning' => 'Warning', 'pending_review' => 'Pending Review'] as $key => $label)
                        <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800/60">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p>
                            <p class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">{{ number_format($summary['risk_summary'][$key] ?? 0) }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    @foreach ($summary['charts'] as $chart)
                        <article class="rounded-xl border border-slate-100 p-4 dark:border-slate-800">
                            <h3 class="text-sm font-semibold text-slate-950 dark:text-white">{{ $chart['title'] }}</h3>
                            <div class="mt-3 space-y-2">
                                @foreach ($chart['segments'] as $segment)
                                    <div>
                                        <div class="flex items-center justify-between text-xs text-slate-500">
                                            <span>{{ $segment['label'] }}</span>
                                            <span>{{ number_format($segment['value']) }}</span>
                                        </div>
                                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                            <div class="h-full rounded-full bg-cyan-500" style="width: {{ min(100, max(8, (int) $segment['value'] * 10)) }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Recent Integrity Runs</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($summary['recent_integrity_runs'] as $run)
                        <article class="rounded-xl bg-slate-50 p-3 text-sm dark:bg-slate-800/60">
                            <p class="font-semibold text-slate-950 dark:text-white">{{ str($run->status)->headline() }} · {{ $run->engine_version }}</p>
                            <p class="mt-1 text-slate-500">{{ number_format($run->rules_executed) }} rules · {{ number_format($run->indicators_created) }} indicators</p>
                        </article>
                    @empty
                        <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500 dark:bg-slate-800/60">No integrity runs have been recorded yet.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Top Agencies</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($summary['top_agencies'] as $agency)
                        <p class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/60">
                            <span>{{ $agency->name }}</span>
                            <span class="font-semibold">{{ number_format($agency->projects_count ?? 0) }} projects</span>
                        </p>
                    @empty
                        <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500 dark:bg-slate-800/60">No agency activity yet.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Top Contractors</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($summary['top_contractors'] as $contractor)
                        <p class="rounded-xl bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/60">{{ $contractor->legal_name }}</p>
                    @empty
                        <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500 dark:bg-slate-800/60">No contractor records yet.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Recent Alerts</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($summary['recent_alerts'] as $alert)
                        <article class="rounded-xl bg-slate-50 p-3 text-sm dark:bg-slate-800/60">
                            <p class="font-semibold text-slate-950 dark:text-white">{{ $alert->title }}</p>
                            <p class="mt-1 text-slate-500">{{ $alert->message }}</p>
                        </article>
                    @empty
                        <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500 dark:bg-slate-800/60">No active analytics alerts.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </section>
</x-layouts.app>
