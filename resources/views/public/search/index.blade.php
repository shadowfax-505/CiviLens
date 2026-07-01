<x-layouts.app title="Public Search">
    <section class="space-y-6">
        <h1 class="text-3xl font-bold">Public Search</h1>
        <form method="GET" class="grid gap-3 md:grid-cols-4">
            <input name="q" value="{{ $filters['q'] ?? '' }}" class="md:col-span-2 rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950" placeholder="Search public records">
            <select name="module" class="rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                <option value="">All modules</option>
                @foreach (['projects', 'procurement', 'documents', 'agencies', 'contractors'] as $module)
                    <option value="{{ $module }}" @selected(($filters['module'] ?? '') === $module)>{{ str($module)->headline() }}</option>
                @endforeach
            </select>
            <button class="rounded-lg bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950">Search</button>
        </form>
        @forelse ($results as $result)
            <article class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-semibold">{{ $result->title }}</h2>
                <p class="mt-1 text-xs uppercase text-slate-500">{{ $result->module }}</p>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $result->description }}</p>
            </article>
        @empty
            <p class="text-slate-500">No public results match your search.</p>
        @endforelse
        {{ $results->links() }}
    </section>
</x-layouts.app>
