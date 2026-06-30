<x-layouts.app title="Knowledge View">
    <section class="space-y-8">
        <div class="rounded-3xl bg-slate-950 p-8 text-white shadow-xl">
            <p class="text-sm uppercase tracking-[0.3em] text-emerald-300">Relationship Explorer</p>
            <h1 class="mt-2 text-4xl font-black">Knowledge View</h1>
            <p class="mt-3 max-w-3xl text-slate-300">{{ $graph['entity']->title }}</p>
        </div>

        <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-cyan-700 dark:text-cyan-300">{{ str($graph['entity']->module)->headline() }}</p>
                    <h2 class="mt-1 text-2xl font-black">{{ $graph['entity']->title }}</h2>
                    @if ($graph['entity']->description)
                        <p class="mt-2 text-slate-600 dark:text-slate-300">{{ $graph['entity']->description }}</p>
                    @endif
                </div>
                @if ($graph['entity']->url)
                    <a class="rounded-full bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-950" href="{{ $graph['entity']->url }}">Open Source Record</a>
                @endif
            </div>
        </article>

        <div class="grid gap-6 lg:grid-cols-2">
            @forelse ($graph['relationships'] as $group => $items)
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-xl font-black">{{ str($group)->headline() }}</h2>
                    <div class="mt-4 space-y-3">
                        @forelse ($items as $item)
                            @php($knowledgeModule = $item->metadata['route_module'] ?? $item->module)
                            <a class="block rounded-2xl bg-slate-100 p-4 hover:bg-cyan-50 dark:bg-slate-800 dark:hover:bg-slate-700" href="{{ route('admin.search.knowledge', ['module' => $knowledgeModule, 'id' => $item->searchableId]) }}">
                                <span class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-700 dark:text-emerald-300">{{ str($item->module)->headline() }}</span>
                                <span class="mt-1 block font-black">{{ $item->title }}</span>
                                @if ($item->description)
                                    <span class="mt-1 block text-sm text-slate-600 dark:text-slate-300">{{ str($item->description)->limit(120) }}</span>
                                @endif
                            </a>
                        @empty
                            <p class="rounded-2xl border border-dashed border-slate-300 p-4 text-sm text-slate-500 dark:border-slate-700">No related records indexed yet.</p>
                        @endforelse
                    </div>
                </section>
            @empty
                <section class="rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="text-xl font-black">No graph relationships available</h2>
                    <p class="mt-2 text-slate-500">Relationships will appear when this entity is connected to indexed projects, budgets, procurement, documents, agencies, or geography.</p>
                </section>
            @endforelse
        </div>
    </section>
</x-layouts.app>
