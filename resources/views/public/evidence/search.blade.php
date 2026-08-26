<x-layouts.app title="Search the evidence">
    <section class="space-y-6">
        <header class="space-y-2">
            <h1 class="text-3xl font-bold">Search the evidence</h1>
            <p class="max-w-3xl text-sm text-slate-600 dark:text-slate-400">
                Figures and document pages from the published snapshot. Type a number in either
                Bengali or Latin digits: many pages in this corpus store Bengali numerals as the
                Latin characters that render them, so a search is run in both.
            </p>
        </header>

        <form method="GET" class="flex gap-3">
            <input name="q" value="{{ $query }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950" placeholder="৫,১৪,০৫১ or 514,051">
            <button class="rounded-lg bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950">Search</button>
        </form>

        @if ($query !== '')
            <p class="text-sm text-slate-500">
                Searched for
                @foreach ($searched_as as $form)<span class="font-mono">{{ $form }}</span>@if (! $loop->last) and @endif @endforeach
                @if ($snapshot) in snapshot <span class="font-mono">{{ $snapshot }}</span>@endif.
            </p>

            <section>
                <h2 class="font-semibold">Certified figures ({{ $figures->count() }})</h2>
                @forelse ($figures as $figure)
                    <article class="mt-3 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                        <p class="font-mono text-lg">{{ $figure->extracted_value }}</p>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ $figure->document?->publisher_name }} ·
                            page {{ $figure->page_number }} ·
                            &alpha; = {{ $figure->certified_alpha }}
                        </p>
                        <p class="mt-2 text-sm">
                            <a class="underline" href="{{ route('public.evidence.figure.page', $figure) }}" target="_blank" rel="noopener">see it on the page</a>
                            @if ($figure->document)
                                · <a class="underline" href="{{ route('public.evidence.show', $figure->document) }}">the document</a>
                            @endif
                        </p>
                    </article>
                @empty
                    <p class="mt-2 text-sm text-slate-500">No certified figure matches. A figure the system declined to stand behind is not searchable here, and that is deliberate.</p>
                @endforelse
            </section>

            <section>
                <h2 class="font-semibold">Document pages ({{ count($pages) }})</h2>
                @forelse ($pages as $hit)
                    <article class="mt-3 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-sm font-semibold">
                            {{ $hit['document']->original_filename ?: $hit['document']->source_url }} · page {{ $hit['page_number'] }}
                        </p>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">…{{ $hit['excerpt'] }}…</p>
                        <p class="mt-2 text-sm"><a class="underline" href="{{ route('public.evidence.show', $hit['document']) }}">the document</a></p>
                    </article>
                @empty
                    <p class="mt-2 text-sm text-slate-500">No published document page carries those characters.</p>
                @endforelse
            </section>
        @endif
    </section>
</x-layouts.app>
