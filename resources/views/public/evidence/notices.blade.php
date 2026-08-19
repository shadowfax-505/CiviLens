<x-layouts.app title="Tender notices">
    <section class="space-y-6">
        <header class="space-y-2">
            <h1 class="text-3xl font-bold">Tender notices</h1>
            <p class="max-w-3xl text-slate-600 dark:text-slate-400">
                These are listing rows as the publisher wrote them. They carry no buyer, no supplier
                and no contract value, because the source does not publish those in a listing. Nothing
                here is a contract record and none of it is certified.
            </p>
            <p class="text-sm text-slate-500">
                Snapshot <span class="font-mono">{{ $snapshot->label }}</span> ·
                {{ number_format($snapshot->notice_count) }} notices
            </p>
        </header>

        <form method="GET" class="flex gap-3">
            <input name="q" value="{{ $filters['q'] ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950" placeholder="Search by reference number">
            <button class="rounded-lg bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950">Search</button>
        </form>

        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3">Nature</th>
                        <th class="px-4 py-3">Status as listed</th>
                        <th class="px-4 py-3">Published</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($notices as $notice)
                        <tr class="border-t border-slate-100 dark:border-slate-800">
                            <td class="px-4 py-2 font-mono text-xs">{{ $notice->reference_number }}</td>
                            <td class="px-4 py-2">{{ $notice->procurement_nature }}</td>
                            <td class="px-4 py-2 text-slate-500">{{ $notice->status }}</td>
                            <td class="px-4 py-2">{{ $notice->published_on?->toDateString() ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $notices->links() }}
    </section>
</x-layouts.app>
