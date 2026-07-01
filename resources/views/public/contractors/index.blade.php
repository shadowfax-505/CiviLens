<x-layouts.app title="Public Contractors">
    <section class="space-y-6">
        <h1 class="text-3xl font-bold">Public Contractors</h1>
        <form method="GET" class="flex gap-3">
            <input name="q" value="{{ $filters['q'] ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950" placeholder="Search contractors">
            <button class="rounded-lg bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950">Search</button>
        </form>
        <div class="grid gap-4 md:grid-cols-2">
            @forelse ($contractors as $organization)
                <article class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <a class="text-lg font-semibold" href="{{ route('public.contractors.show', $organization) }}">{{ $organization->legal_name }}</a>
                    <p class="mt-1 text-sm text-slate-500">{{ $organization->companyType?->name }} · {{ $organization->industry?->name }}</p>
                </article>
            @empty
                <p class="text-slate-500">No public contractors match the current filters.</p>
            @endforelse
        </div>
        {{ $contractors->links() }}
    </section>
</x-layouts.app>
