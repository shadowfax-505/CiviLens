<x-layouts.app title="CivicLens Intelligence">
    <section class="space-y-8">
        <div class="rounded-3xl bg-slate-950 p-8 text-white shadow-sm dark:bg-slate-900">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.24em] text-amber-300">Intelligence Readiness</p>
                    <h1 class="mt-3 text-4xl font-bold tracking-tight">Evidence Review & Risk Indicators</h1>
                    <p class="mt-3 max-w-3xl text-slate-300">Rule-based, source-backed signals for human review. No OCR extraction, LLM decisions, embeddings, or legal conclusions are generated in v1.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('admin.intelligence.indicators.index') }}" class="rounded-full bg-white px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-amber-50">Review Indicators</a>
                    <a href="{{ route('admin.intelligence.rules.index') }}" class="rounded-full bg-white/10 px-4 py-2 text-sm font-semibold text-white ring-1 ring-white/20 hover:bg-white/15">Rule Management Console</a>
                    <a href="{{ route('admin.intelligence.processing-jobs.index') }}" class="rounded-full bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-400">Processing Queue</a>
                    <form method="POST" action="{{ route('admin.intelligence.engine.run') }}">
                        @csrf
                        <button class="rounded-full bg-emerald-500 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-400">Run Civic Integrity Engine</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            @foreach ($summary['cards'] as $label => $value)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">{{ str($label)->headline() }}</p>
                    <p class="mt-3 text-3xl font-bold text-slate-950 dark:text-white">{{ number_format($value) }}</p>
                </article>
            @endforeach
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Civic Integrity Engine</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Deterministic, evidence-backed analysis for human review. The engine records thresholds, rule versions, and run metadata.</p>
                </div>
                @if ($summary['latest_engine_run'])
                    <div class="rounded-xl bg-slate-50 px-4 py-3 text-sm dark:bg-slate-800/60">
                        <p class="font-semibold text-slate-950 dark:text-white">{{ str($summary['latest_engine_run']->status)->headline() }} run</p>
                        <p class="mt-1 text-slate-500 dark:text-slate-400">{{ number_format($summary['latest_engine_run']->rules_executed) }} rules · {{ number_format($summary['latest_engine_run']->indicators_created) }} indicators · {{ $summary['latest_engine_run']->started_at?->diffForHumans() }}</p>
                    </div>
                @else
                    <p class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">No engine runs recorded yet.</p>
                @endif
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-3">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 xl:col-span-2">
                <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Integrity Timeline</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="py-3 pr-4">Started</th>
                                <th class="py-3 pr-4">Status</th>
                                <th class="py-3 pr-4">Rules</th>
                                <th class="py-3 pr-4">Indicators</th>
                                <th class="py-3 pr-4">Engine</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($summary['integrity_timeline'] as $run)
                                <tr>
                                    <td class="py-3 pr-4">{{ $run->started_at?->toDayDateTimeString() }}</td>
                                    <td class="py-3 pr-4">{{ str($run->status)->headline() }}</td>
                                    <td class="py-3 pr-4">{{ number_format($run->rules_executed) }}</td>
                                    <td class="py-3 pr-4">{{ number_format($run->indicators_created) }}</td>
                                    <td class="py-3 pr-4">{{ $run->engine_version }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-6 text-center text-slate-500">No integrity timeline is available yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Performance Metrics</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Average Run</dt>
                        <dd class="mt-1 text-lg font-bold text-slate-950 dark:text-white">{{ number_format($summary['performance']['average_run_ms']) }} ms</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rules With Execution Data</dt>
                        <dd class="mt-1 text-lg font-bold text-slate-950 dark:text-white">{{ number_format($summary['performance']['rules_with_execution_data']) }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Failed Runs 24h</dt>
                        <dd class="mt-1 text-lg font-bold text-slate-950 dark:text-white">{{ number_format($summary['performance']['failed_runs_24h']) }}</dd>
                    </div>
                </dl>
            </section>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Rule Execution History</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($summary['rule_execution_history'] as $rule)
                        <article class="rounded-xl bg-slate-50 p-3 text-sm dark:bg-slate-800/60">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-slate-950 dark:text-white">{{ $rule->name }}</p>
                                    <p class="mt-1 text-slate-500">{{ str($rule->module)->headline() }} · {{ $rule->version }}</p>
                                </div>
                                <span class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-900 dark:text-slate-300">{{ number_format($rule->indicators_count) }} indicators</span>
                            </div>
                        </article>
                    @empty
                        <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500 dark:bg-slate-800/60">No rule executions have been recorded.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Indicator Distribution</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    @foreach (['severity_distribution' => 'Severity', 'status_distribution' => 'Status', 'module_distribution' => 'Module'] as $key => $label)
                        <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                            <h3 class="text-sm font-semibold text-slate-950 dark:text-white">{{ $label }}</h3>
                            <div class="mt-3 space-y-2">
                                @forelse ($summary['charts'][$key] as $name => $value)
                                    <p class="flex items-center justify-between text-xs text-slate-500"><span>{{ str((string) $name)->headline() }}</span><span>{{ number_format($value) }}</span></p>
                                @empty
                                    <p class="text-xs text-slate-500">No data</p>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Agency Risk Ranking</h2>
                <div class="mt-4 space-y-2">
                    @forelse ($summary['rankings']['agencies'] as $agency)
                        <p class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/60"><span>{{ $agency->name }}</span><span>{{ number_format($agency->projects_count ?? 0) }} projects</span></p>
                    @empty
                        <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500 dark:bg-slate-800/60">No agency ranking data yet.</p>
                    @endforelse
                </div>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Document Completeness</h2>
                <div class="mt-4 grid gap-3 text-sm">
                    <p class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">Complete metadata: <strong>{{ number_format($summary['rankings']['documents']['complete']) }}</strong></p>
                    <p class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">Metadata gaps: <strong>{{ number_format($summary['rankings']['documents']['metadata_gaps']) }}</strong></p>
                </div>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Citizen Report Correlations</h2>
                <div class="mt-4 space-y-2">
                    @forelse ($summary['rankings']['citizen_reports'] as $cluster)
                        <p class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/60"><span>Project #{{ $cluster->project_id }}</span><span>{{ number_format($cluster->aggregate) }} reports</span></p>
                    @empty
                        <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500 dark:bg-slate-800/60">No report clusters yet.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="grid gap-6 xl:grid-cols-4">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Contractor Risk Ranking</h2>
                <div class="mt-4 space-y-2">
                    @forelse ($summary['rankings']['contractors'] as $contractor)
                        <p class="rounded-xl bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/60">{{ $contractor->legal_name }}</p>
                    @empty
                        <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500 dark:bg-slate-800/60">No contractor ranking data yet.</p>
                    @endforelse
                </div>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Project Risk Ranking</h2>
                <div class="mt-4 space-y-2">
                    @forelse ($summary['rankings']['projects'] as $project)
                        <p class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/60"><span>{{ $project->name }}</span><span>{{ $project->progress_percentage }}%</span></p>
                    @empty
                        <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500 dark:bg-slate-800/60">No project ranking data yet.</p>
                    @endforelse
                </div>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Budget Risk Ranking</h2>
                <div class="mt-4 space-y-2">
                    @forelse ($summary['rankings']['budgets'] as $budget)
                        <p class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/60"><span>{{ $budget->budget_code ?? 'Budget #'.$budget->id }}</span><span>{{ number_format((float) $budget->actual_expenditure) }}</span></p>
                    @empty
                        <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500 dark:bg-slate-800/60">No budget ranking data yet.</p>
                    @endforelse
                </div>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Risk Heatmap</h2>
                <p class="mt-1 text-sm text-slate-500">Geographic Summary</p>
                <div class="mt-4 space-y-2">
                    @forelse ($summary['rankings']['geography'] as $region)
                        <p class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/60"><span>Division #{{ $region->division_id }}</span><span>{{ number_format($region->aggregate) }} projects</span></p>
                    @empty
                        <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500 dark:bg-slate-800/60">No geographic summary data yet.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:col-span-2">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Recent Indicators</h2>
                    <a href="{{ route('admin.intelligence.indicators.index') }}" class="text-sm font-semibold text-amber-600 dark:text-amber-300">View all</a>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="py-3 pr-4">Signal</th>
                                <th class="py-3 pr-4">Severity</th>
                                <th class="py-3 pr-4">Status</th>
                                <th class="py-3 pr-4">Rule</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($summary['recent_indicators'] as $indicator)
                                <tr>
                                    <td class="py-3 pr-4">
                                        <a href="{{ route('admin.intelligence.indicators.show', $indicator) }}" class="font-semibold text-slate-950 hover:text-amber-700 dark:text-white">{{ $indicator->title }}</a>
                                        <p class="text-xs text-slate-500">{{ $indicator->module }}</p>
                                    </td>
                                    <td class="py-3 pr-4">{{ str($indicator->severity)->headline() }}</td>
                                    <td class="py-3 pr-4">{{ str($indicator->status)->headline() }}</td>
                                    <td class="py-3 pr-4">{{ $indicator->rule?->name }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-6 text-center text-slate-500">No intelligence indicators have been generated yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Rule Run Panel</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($rules as $rule)
                        <form method="POST" action="{{ route('admin.intelligence.rules.run', $rule) }}" class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                            @csrf
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $rule->name }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ str($rule->module)->headline() }} · {{ $rule->version }}</p>
                            <button class="mt-3 rounded-lg bg-slate-950 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 dark:bg-white dark:text-slate-950">Run Rule</button>
                        </form>
                    @empty
                        <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500 dark:bg-slate-800/60">No active intelligence rules are configured.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </section>
</x-layouts.app>
