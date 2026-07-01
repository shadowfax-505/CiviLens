<article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $metric['label'] }}</p>
            <p class="mt-3 text-3xl font-bold tracking-tight text-slate-950 dark:text-white">
                @if (($metric['unit'] ?? null) === 'BDT')
                    {{ number_format((float) $metric['value'], 2) }}
                @elseif (($metric['unit'] ?? null) === '%')
                    {{ number_format((float) $metric['value'], 2) }}%
                @else
                    {{ is_numeric($metric['value']) ? number_format((float) $metric['value'], 2) : $metric['value'] }}
                @endif
            </p>
        </div>
        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-500/10 dark:text-blue-200">
            {{ str($metric['category'])->headline() }}
        </span>
    </div>
    @if ($metric['description'] ?? null)
        <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">{{ $metric['description'] }}</p>
    @endif
</article>
