<x-layouts.app title="Search Analytics">
    <section class="space-y-8">
        <div>
            <p class="text-sm font-bold uppercase tracking-[0.25em] text-cyan-700 dark:text-cyan-300">Discovery Intelligence</p>
            <h1 class="mt-2 text-4xl font-black">Search Analytics Dashboard</h1>
            <p class="mt-2 text-slate-600 dark:text-slate-300">Operational insight into search usage, latency, failures, saved searches, and click-through behavior.</p>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            @foreach ([
                'Total Searches' => $summary['total_searches'],
                'Failed Searches' => $summary['failed_searches'],
                'Average Latency' => $summary['average_latency_ms'].' ms',
                'Saved Searches' => $summary['saved_searches'],
                'Result Clicks' => $summary['clicks'],
            ] as $label => $value)
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-sm font-semibold text-slate-500">{{ $label }}</p>
                    <p class="mt-2 text-3xl font-black">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-xl font-black">Most Searched Terms</h2>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                @forelse ($summary['top_terms'] as $term => $count)
                    <div class="flex items-center justify-between rounded-2xl bg-slate-100 px-4 py-3 dark:bg-slate-800">
                        <span class="font-semibold">{{ $term }}</span>
                        <span class="rounded-full bg-cyan-100 px-3 py-1 text-sm font-black text-cyan-900">{{ $count }}</span>
                    </div>
                @empty
                    <p class="text-slate-500">Search trends will appear after users search.</p>
                @endforelse
            </div>
        </div>
    </section>
</x-layouts.app>
