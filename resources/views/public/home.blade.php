<x-layouts.app title="Public Transparency">
    <section class="space-y-8">
        <div>
            <p class="text-sm font-semibold uppercase text-emerald-700">Public Transparency</p>
            <h1 class="mt-2 text-4xl font-bold text-slate-950 dark:text-white">CivicLens Public Portal</h1>
            <p class="mt-3 max-w-3xl text-slate-600 dark:text-slate-300">Browse approved public civic records and track citizen reports through moderated workflows.</p>
        </div>
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm text-slate-500">Public Projects</p>
                <p class="mt-2 text-3xl font-bold">{{ $summary['public_projects'] }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm text-slate-500">Public Tenders</p>
                <p class="mt-2 text-3xl font-bold">{{ $summary['public_tenders'] }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm text-slate-500">Public Documents</p>
                <p class="mt-2 text-3xl font-bold">{{ $summary['public_documents'] }}</p>
            </div>
        </div>
        <div class="grid gap-3 md:grid-cols-5">
            @foreach ($navigation as $link)
                <a class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-semibold hover:border-emerald-300 dark:border-slate-800 dark:bg-slate-900" href="{{ $link['route'] }}">{{ $link['label'] }}</a>
            @endforeach
        </div>
    </section>
</x-layouts.app>
