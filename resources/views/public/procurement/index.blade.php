<x-layouts.app title="Public Procurement">
    <section class="space-y-6">
        <h1 class="text-3xl font-bold">Public Procurement</h1>
        <form method="GET" class="flex gap-3">
            <input name="q" value="{{ $filters['q'] ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950" placeholder="Search tenders">
            <button class="rounded-lg bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950">Search</button>
        </form>
        @forelse ($tenders as $tender)
            <article class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-semibold">{{ $tender->title }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $tender->tender_number }} · {{ $tender->agency?->name }} · {{ $tender->status?->name }}</p>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ str($tender->description)->limit(180) }}</p>
                @foreach ($tender->awards as $award)
                    <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-950 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100">
                        <span class="font-semibold">Award notice:</span>
                        {{ $award->bidSubmission?->bidderOrganization?->name ?? 'Winning bidder pending publication' }}
                        @if ($award->contract)
                            · {{ $award->contract->contract_number }} · {{ $award->contract->status }}
                        @endif
                    </div>
                @endforeach
            </article>
        @empty
            <p class="text-slate-500">No public procurement records match the current filters.</p>
        @endforelse
        {{ $tenders->links() }}
    </section>
</x-layouts.app>
