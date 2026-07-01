<x-layouts.app :title="$agency->name">
    <section class="space-y-8">
        <div>
            <p class="text-sm font-semibold uppercase text-emerald-700">{{ $agency->type?->name }}</p>
            <h1 class="mt-2 text-3xl font-bold">{{ $agency->name }}</h1>
            <p class="mt-2 text-slate-600 dark:text-slate-300">{{ $agency->description }}</p>
        </div>
        <div class="grid gap-6 md:grid-cols-2">
            <section class="space-y-3">
                <h2 class="text-xl font-semibold">Public Projects</h2>
                @forelse ($projects as $project)
                    <a class="block rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900" href="{{ route('public.projects.show', $project) }}">{{ $project->name }}</a>
                @empty
                    <p class="text-sm text-slate-500">No public projects are available.</p>
                @endforelse
            </section>
            <section class="space-y-3">
                <h2 class="text-xl font-semibold">Public Procurement</h2>
                @forelse ($tenders as $tender)
                    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">{{ $tender->title }}</div>
                @empty
                    <p class="text-sm text-slate-500">No public procurement records are available.</p>
                @endforelse
            </section>
        </div>
    </section>
</x-layouts.app>
