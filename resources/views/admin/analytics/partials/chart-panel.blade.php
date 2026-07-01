<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900" data-chart-definition='@json($chart)'>
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-slate-950 dark:text-white">{{ $chart['title'] }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ str($chart['type'])->headline() }} chart-ready data</p>
        </div>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $chart['type'] }}</span>
    </div>
    <div class="mt-5 space-y-3" role="img" aria-label="{{ $chart['title'] }}">
        @php
            $values = collect($chart['data']['datasets'][0]['data'] ?? [])->map(fn ($value) => (float) $value);
            $max = max($values->max() ?: 1, 1);
        @endphp
        @foreach (($chart['data']['labels'] ?? []) as $index => $label)
            @php
                $value = (float) ($chart['data']['datasets'][0]['data'][$index] ?? 0);
                $width = max(4, min(100, ($value / $max) * 100));
            @endphp
            <div>
                <div class="mb-1 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                    <span>{{ $label }}</span>
                    <span>{{ number_format($value, 2) }}</span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                    <div class="h-full rounded-full bg-blue-600" style="width: {{ $width }}%"></div>
                </div>
            </div>
        @endforeach
        @if (empty($chart['data']['labels']))
            <p class="rounded-xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">No chart data for the selected filters.</p>
        @endif
    </div>
</section>
