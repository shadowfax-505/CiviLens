<x-layouts.app title="CivicLens Dashboard">
    @php($summary = $summary ?? ['cards' => [], 'risk_summary' => [], 'system_health' => ['status' => 'unknown'], 'recent_integrity_runs' => collect(), 'recent_alerts' => collect(), 'top_agencies' => collect(), 'top_contractors' => collect(), 'charts' => []])

    <section class="space-y-8">
        <div class="cl-page-hero">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="cl-kicker text-cyan-100">Executive Command Center</p>
                    <h1 class="cl-page-title mt-3">CivicLens Dashboard</h1>
                    <p class="cl-page-copy">CivicLens v1.0.0 is ready for production use across delivery, finance, procurement, documents, citizen engagement, and deterministic integrity signals.</p>
                </div>
                <div class="rounded-2xl border border-white/20 bg-white/15 px-4 py-3 text-sm backdrop-blur">
                    <h2 class="font-semibold">System Health</h2>
                    <p class="mt-1 text-cyan-100">{{ str($summary['system_health']['status'] ?? 'unknown')->upper() }}</p>
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($summary['cards'] as $card)
                <article class="cl-card transition hover:-translate-y-0.5">
                    <p class="cl-kicker">{{ $card['label'] }}</p>
                    <p class="mt-3 text-3xl font-bold text-slate-950 dark:text-white">{{ $card['value'] }}</p>
                    <p class="mt-2 text-sm cl-muted">{{ $card['detail'] }}</p>
                </article>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <section class="cl-card xl:col-span-2">
                <h2 class="cl-card-title">Risk Summary</h2>
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

            <section class="cl-card">
                <h2 class="cl-card-title">Recent Integrity Runs</h2>
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
            <section class="cl-card">
                <h2 class="cl-card-title">Top Agencies</h2>
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

            <section class="cl-card">
                <h2 class="cl-card-title">Top Contractors</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($summary['top_contractors'] as $contractor)
                        <p class="rounded-xl bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/60">{{ $contractor->legal_name }}</p>
                    @empty
                        <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500 dark:bg-slate-800/60">No contractor records yet.</p>
                    @endforelse
                </div>
            </section>

            <section class="cl-card">
                <h2 class="cl-card-title">Recent Alerts</h2>
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
