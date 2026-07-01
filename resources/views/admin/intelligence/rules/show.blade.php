<x-layouts.app title="Manage Intelligence Rule">
    <section class="space-y-8">
        <div class="rounded-3xl bg-slate-950 p-8 text-white shadow-sm dark:bg-slate-900">
            <p class="text-sm font-semibold uppercase tracking-[0.24em] text-amber-300">Rule Management Console</p>
            <h1 class="mt-3 text-4xl font-bold tracking-tight">{{ $rule->name }}</h1>
            <p class="mt-3 max-w-3xl text-slate-300">{{ $rule->description ?: 'Configure deterministic thresholds and documentation for this source-backed rule.' }}</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:col-span-2">
                <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Rule Settings</h2>
                <form method="POST" action="{{ route('admin.intelligence.rules.update', $rule) }}" class="mt-5 grid gap-4 md:grid-cols-2">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="is_active" value="0">
                    <label class="flex items-center gap-3 rounded-xl bg-slate-50 px-3 py-3 text-sm font-medium text-slate-700 dark:bg-slate-800/60 dark:text-slate-200">
                        <input type="checkbox" name="is_active" value="1" @checked($rule->is_active) class="rounded border-slate-300 text-amber-600">
                        Enabled
                    </label>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                        Severity
                        <select name="severity_default" class="mt-1 w-full rounded-lg border-slate-300 bg-white text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            @foreach (['info', 'warning', 'critical'] as $severity)
                                <option value="{{ $severity }}" @selected($rule->severity_default === $severity)>{{ str($severity)->headline() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                        Priority
                        <input type="number" name="priority" min="0" max="1000" value="{{ old('priority', $rule->priority ?? 100) }}" class="mt-1 w-full rounded-lg border-slate-300 bg-white text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    </label>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                        Weight
                        <input type="number" name="weight" min="0" max="100" value="{{ old('weight', $rule->weight ?? 50) }}" class="mt-1 w-full rounded-lg border-slate-300 bg-white text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    </label>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                        Execution Frequency
                        <select name="execution_frequency" class="mt-1 w-full rounded-lg border-slate-300 bg-white text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            @foreach (['manual', 'hourly', 'daily', 'weekly', 'monthly'] as $frequency)
                                <option value="{{ $frequency }}" @selected(($rule->execution_frequency ?? 'manual') === $frequency)>{{ str($frequency)->headline() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                        Documentation URL
                        <input type="url" name="documentation_url" value="{{ old('documentation_url', $rule->documentation_url) }}" class="mt-1 w-full rounded-lg border-slate-300 bg-white text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    </label>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200 md:col-span-2">
                        Thresholds JSON
                        <textarea name="thresholds" rows="5" class="mt-1 w-full rounded-lg border-slate-300 bg-white font-mono text-sm text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('thresholds', json_encode($rule->thresholds ?? [], JSON_PRETTY_PRINT)) }}</textarea>
                    </label>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200 md:col-span-2">
                        Description
                        <textarea name="description" rows="4" class="mt-1 w-full rounded-lg border-slate-300 bg-white text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('description', $rule->description) }}</textarea>
                    </label>
                    <div class="flex flex-wrap gap-3 md:col-span-2">
                        <button class="rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-500">Save Rule</button>
                    </div>
                </form>
            </section>

            <aside class="space-y-6">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Execution</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Last Execution</dt>
                            <dd class="mt-1 text-slate-900 dark:text-white">{{ $rule->last_executed_at?->toDayDateTimeString() ?? 'Not run' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Duration</dt>
                            <dd class="mt-1 text-slate-900 dark:text-white">{{ $rule->last_execution_ms ? number_format($rule->last_execution_ms).' ms' : 'Not recorded' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Version</dt>
                            <dd class="mt-1 text-slate-900 dark:text-white">{{ $rule->version }}</dd>
                        </div>
                    </dl>
                    <form method="POST" action="{{ route('admin.intelligence.rules.run', $rule) }}" class="mt-4">
                        @csrf
                        <button class="w-full rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 dark:bg-white dark:text-slate-950">Run Rule</button>
                    </form>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Audit History</h2>
                    <div class="mt-4 space-y-3">
                        @forelse ($rule->audits->sortByDesc('occurred_at')->take(8) as $audit)
                            <article class="rounded-xl bg-slate-50 p-3 text-sm dark:bg-slate-800/60">
                                <p class="font-semibold text-slate-950 dark:text-white">{{ str($audit->event)->headline() }}</p>
                                <p class="mt-1 text-slate-500">{{ $audit->occurred_at?->diffForHumans() }} · {{ $audit->actor?->name ?? 'System' }}</p>
                            </article>
                        @empty
                            <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500 dark:bg-slate-800/60">No audit events recorded yet.</p>
                        @endforelse
                    </div>
                </section>
            </aside>
        </div>
    </section>
</x-layouts.app>
