<x-layouts.app title="Universal Search">
    <section class="space-y-8">
        <div class="rounded-3xl bg-gradient-to-br from-cyan-950 via-slate-900 to-emerald-950 p-8 text-white shadow-xl">
            <p class="text-sm uppercase tracking-[0.3em] text-cyan-200">CivicLens Discovery</p>
            <div class="mt-3 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h1 class="text-4xl font-black tracking-tight">Universal Search</h1>
                    <p class="mt-2 max-w-2xl text-cyan-50">Search once across projects, budgets, procurement, contractors, agencies, geography, and documents.</p>
                </div>
                @if ($canManageSearch)
                    <a class="rounded-full border border-white/30 px-4 py-2 text-sm font-semibold text-white hover:bg-white/10" href="{{ route('admin.search.analytics') }}">Analytics Dashboard</a>
                @endif
            </div>

            <form class="mt-8 grid gap-3 lg:grid-cols-[1fr_180px_160px_auto]" method="GET" action="{{ route('admin.search.index') }}">
                <label class="sr-only" for="q">Search</label>
                <input id="q" name="q" value="{{ $query->query }}" list="search-suggestions" class="rounded-2xl border border-white/10 bg-white/95 px-4 py-3 text-slate-950 shadow-inner focus:border-cyan-300 focus:outline-none focus:ring-4 focus:ring-cyan-300/30" placeholder="Search projects, contractors, documents..." autocomplete="off">
                <datalist id="search-suggestions">
                    @foreach ($suggestions as $suggestion)
                        <option value="{{ $suggestion }}"></option>
                    @endforeach
                </datalist>

                <select name="module" class="rounded-2xl border border-white/10 bg-white/95 px-4 py-3 text-slate-950 focus:border-cyan-300 focus:outline-none focus:ring-4 focus:ring-cyan-300/30 dark:bg-slate-950">
                    <option value="">All modules</option>
                    @foreach ($modules as $module)
                        <option value="{{ $module }}" @selected($query->module === $module)>{{ str($module)->headline() }}</option>
                    @endforeach
                </select>

                <select name="sort" class="rounded-2xl border border-white/10 bg-white/95 px-4 py-3 text-slate-950 focus:border-cyan-300 focus:outline-none focus:ring-4 focus:ring-cyan-300/30 dark:bg-slate-950">
                    <option value="relevance" @selected($query->sort === 'relevance')>Relevance</option>
                    <option value="title" @selected($query->sort === 'title')>Title</option>
                    <option value="module" @selected($query->sort === 'module')>Module</option>
                    <option value="status" @selected($query->sort === 'status')>Status</option>
                </select>

                <button class="rounded-2xl bg-cyan-300 px-5 py-3 font-black text-cyan-950 shadow-lg shadow-cyan-950/30 hover:bg-cyan-200">Search</button>
            </form>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_280px]">
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">{{ $results->total() }} results found</p>
                    <a class="text-sm font-semibold text-cyan-700 dark:text-cyan-300" href="{{ route('admin.search.advanced', request()->query()) }}">Advanced Search Builder</a>
                </div>

                @forelse ($results as $position => $result)
                    @php($knowledgeModule = $result->metadata['route_module'] ?? $result->module)
                    <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-600 dark:text-emerald-300">{{ str($result->module)->headline() }}</p>
                                <h2 class="mt-1 text-xl font-black">
                                    <a aria-label="{{ $result->title }}" href="{{ route('admin.search.knowledge', ['module' => $knowledgeModule, 'id' => $result->searchableId]) }}">
                                        {!! $result->highlights['title'] ?? e($result->title) !!}
                                    </a>
                                </h2>
                                @if ($result->description)
                                    <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">{!! $result->highlights['description'] ?? e($result->description) !!}</p>
                                @endif
                            </div>
                            <div class="flex gap-2">
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $result->visibility }}</span>
                                @if ($result->status)
                                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800 dark:bg-emerald-900 dark:text-emerald-100">{{ $result->status }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-3">
                            @if ($result->metadata['public_url'] ?? $result->url)
                                <a class="rounded-full bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-950" href="{{ $result->metadata['public_url'] ?? $result->url }}">Open Record</a>
                            @endif
                            <a class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold dark:border-slate-700" href="{{ route('admin.search.knowledge', ['module' => $knowledgeModule, 'id' => $result->searchableId]) }}">Knowledge View</a>
                        </div>
                    </article>
                @empty
                    <div class="rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center dark:border-slate-700 dark:bg-slate-900">
                        <h2 class="text-2xl font-black">No matching civic records yet</h2>
                        <p class="mt-2 text-slate-600 dark:text-slate-300">Try a broader keyword, remove a module filter, or index new records through the search queue.</p>
                    </div>
                @endforelse

                {{ $results->links() }}
            </div>

            <aside class="space-y-4">
                <form class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900" method="POST" action="{{ route('admin.search.saved') }}">
                    @csrf
                    <h2 class="text-lg font-black">Save Search</h2>
                    <input type="hidden" name="q" value="{{ $query->query }}">
                    <input type="hidden" name="module" value="{{ $query->module }}">
                    <input type="hidden" name="sort" value="{{ $query->sort }}">
                    <input type="hidden" name="direction" value="{{ $query->direction }}">
                    <label class="mt-4 block text-sm font-semibold" for="saved-name">Name</label>
                    <input id="saved-name" name="name" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-950" placeholder="Bridge monitoring">
                    <button type="submit" class="mt-4 w-full rounded-xl bg-emerald-600 px-4 py-2 font-bold text-white hover:bg-emerald-500">Save</button>
                </form>

                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-lg font-black">Recent Searches</h2>
                    <div class="mt-3 space-y-2 text-sm">
                        @forelse ($recentSearches as $recent)
                            <a class="block rounded-xl bg-slate-100 px-3 py-2 dark:bg-slate-800" href="{{ route('admin.search.index', ['q' => $recent->query, 'module' => $recent->module]) }}">{{ $recent->query ?: 'Blank search' }}</a>
                        @empty
                            <p class="text-slate-500">Recent searches will appear here.</p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-lg font-black">Saved Searches</h2>
                    <div class="mt-3 space-y-2 text-sm">
                        @forelse ($savedSearches as $saved)
                            <a class="block rounded-xl bg-slate-100 px-3 py-2 dark:bg-slate-800" href="{{ route('admin.search.index', ['q' => $saved->query, 'module' => $saved->module, 'sort' => $saved->sort, 'direction' => $saved->direction]) }}">{{ $saved->name }}</a>
                        @empty
                            <p class="text-slate-500">Save useful discovery workflows for later.</p>
                        @endforelse
                    </div>
                </div>
            </aside>
        </div>
    </section>
</x-layouts.app>
