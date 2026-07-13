<x-layouts.app title="Public Agencies">
    <section class="space-y-6">
        <h1 class="text-3xl font-bold">Public Agencies</h1>
        <form method="GET" class="grid gap-3 md:grid-cols-3">
            <input name="q" value="{{ $filters['q'] ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950" placeholder="Search agencies">
            <select name="agency_type_id"><option value="">All agency types</option>@foreach ($agencyTypes as $type)<option value="{{ $type->id }}" @selected(($filters['agency_type_id'] ?? null) == $type->id)>{{ $type->name }}</option>@endforeach</select>
            <button class="rounded-lg bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950">Search</button>
        </form>
        <div class="grid gap-4 md:grid-cols-2">
            @forelse ($agencies as $agency)
                <article class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <a class="text-lg font-semibold" href="{{ route('public.agencies.show', $agency) }}">{{ $agency->name }}</a>
                    <p class="mt-1 text-sm text-slate-500">{{ $agency->type?->name }} · {{ $agency->short_name }}</p>
                    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">{{ str($agency->description)->limit(160) }}</p>
                </article>
            @empty
                <p class="text-slate-500">No public agencies match the current filters.</p>
            @endforelse
        </div>
        {{ $agencies->links() }}
    </section>
</x-layouts.app>
