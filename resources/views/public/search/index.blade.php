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
            @php
                $url = $result->metadata['public_url'] ?? $result->url;
                $status = $result->status;
                $meta = $result->metadata;
            @endphp
            <a href="{{ $url }}" class="group block rounded-lg border border-slate-200 bg-white p-5 transition hover:border-blue-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-blue-700">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="font-semibold group-hover:text-blue-600 dark:group-hover:text-blue-400">{{ $result->title }}</h2>
                    @if ($status)
                        <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-400">{{ $status }}</span>
                    @endif
                </div>
                <p class="mt-1 text-xs uppercase tracking-wide text-slate-500">{{ $result->module }}</p>
                @if ($result->description)
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{!! $result->highlights['description'] ?? e($result->description) !!}</p>
                @endif
                @if (!empty($meta))
                    <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-400">
                        @foreach (['project_code', 'tender_number', 'registration_number', 'uuid', 'file_extension', 'progress_percentage', 'closing_at'] as $key)
                            @if (isset($meta[$key]) && $meta[$key] !== null && $meta[$key] !== '')
                                <span>{{ str_replace('_', ' ', $key) }}: {{ $meta[$key] }}</span>
                            @endif
                        @endforeach
                        @if (isset($meta['file_size']))
                            <span>file size: {{ number_format($meta['file_size'] / 1024, 1) }} KB</span>
                        @endif
                    </div>
                @endif
            </a>
        @empty
            <p class="text-slate-500">No public results match your search.</p>
        @endforelse
        {{ $results->links() }}
    </section>
</x-layouts.app>
