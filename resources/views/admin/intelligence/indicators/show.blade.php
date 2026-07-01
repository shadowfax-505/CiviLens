<x-layouts.app title="Intelligence Indicator">
    <section class="space-y-6">
        <div class="rounded-3xl bg-slate-950 p-8 text-white shadow-sm dark:bg-slate-900">
            <p class="text-sm font-semibold uppercase tracking-[0.24em] text-amber-300">{{ str($indicator->severity)->headline() }} Signal</p>
            <h1 class="mt-3 text-4xl font-bold tracking-tight">{{ $indicator->title }}</h1>
            <p class="mt-3 max-w-3xl text-slate-300">{{ $indicator->description }}</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:col-span-2">
                <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Explainability</h2>
                <dl class="mt-4 grid gap-4 md:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Triggered Rule</dt>
                        <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $explanation['triggered_rule'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rule Version</dt>
                        <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $explanation['rule_version'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Confidence</dt>
                        <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $explanation['confidence_score'] }}%</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Review Status</dt>
                        <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ str($explanation['status'])->headline() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Threshold</dt>
                        <dd class="mt-1 font-mono text-sm text-slate-900 dark:text-white">{{ json_encode($explanation['threshold']) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Actual Value</dt>
                        <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $explanation['actual_value'] ?? 'Recorded in payload' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Expected Value</dt>
                        <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $explanation['expected_value'] ?? 'Threshold dependent' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Engine Version</dt>
                        <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $explanation['engine_version'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Calculation Timestamp</dt>
                        <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $explanation['calculation_timestamp']?->toDayDateTimeString() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Human Review Requirement</dt>
                        <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $explanation['human_review_required'] ? 'Human review required' : 'No review required' }}</dd>
                    </div>
                </dl>

                <div class="mt-6 rounded-xl bg-amber-50 p-4 text-sm text-amber-950 dark:bg-amber-500/10 dark:text-amber-100">
                    <p class="font-semibold">Recommendation</p>
                    <p class="mt-1">{{ $explanation['recommendation'] }}</p>
                </div>

                <h3 class="mt-6 text-base font-semibold text-slate-950 dark:text-white">Evidence</h3>
                <div class="mt-3 space-y-3">
                    @forelse ($explanation['evidence'] as $evidence)
                        <article class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800/60">
                            <p class="font-semibold text-slate-900 dark:text-white">{{ $evidence->label }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $evidence->summary }}</p>
                            <p class="mt-2 text-xs font-semibold text-slate-500">Weight: {{ $evidence->weight }}%</p>
                        </article>
                    @empty
                        <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500 dark:bg-slate-800/60">No evidence records are linked yet.</p>
                    @endforelse
                </div>
            </section>

            <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Human Review</h2>
                <form method="POST" action="{{ route('admin.intelligence.indicators.review', $indicator) }}" class="mt-4 space-y-4">
                    @csrf
                    @method('PATCH')
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">
                        Status
                        <select name="status" class="mt-1 w-full rounded-lg border-slate-300 bg-white text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            @foreach (['in_review', 'accepted', 'dismissed', 'needs_more_evidence'] as $status)
                                <option value="{{ $status }}" @selected($indicator->status === $status)>{{ str($status)->headline() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">
                        Notes
                        <textarea name="notes" rows="5" class="mt-1 w-full rounded-lg border-slate-300 bg-white text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white"></textarea>
                    </label>
                    <button class="w-full rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-500">Save Review</button>
                </form>
            </aside>
        </div>
    </section>
</x-layouts.app>
