<x-layouts.app title="Certified evidence">
    <section class="space-y-8">
        <header class="space-y-3">
            <h1 class="text-3xl font-bold">Certified evidence</h1>
            <p class="max-w-3xl text-slate-600 dark:text-slate-400">
                Every figure below was read from a published document, scored by reading it a second
                time, and accepted only where that score fell inside a calibrated threshold. Values the
                system declined to stand behind are not shown at all.
            </p>
            <p class="text-sm text-slate-500">
                Snapshot <span class="font-mono">{{ $snapshot->label }}</span>,
                published {{ $snapshot->published_at?->toDayDateTimeString() }} ·
                {{ number_format($snapshot->document_count) }} archived items ·
                {{ number_format($snapshot->figure_count) }} certified figures ·
                {{ number_format($snapshot->notice_count) }} tender notices
                @if ($snapshot->skipped_figure_count > 0)
                    · {{ number_format($snapshot->skipped_figure_count) }} certified values could not be traced to a document and are not shown
                @endif
            </p>
        </header>

        <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="font-semibold">What the guarantee covers</h2>
            <p class="mt-1 text-sm text-slate-500">
                A risk level is a property of the group it was calibrated on. A group with few
                adjudicated labels certifies at a looser level than one with many, and a threshold
                borrowed from a wider pool is valid for that pool rather than for the group inside it.
            </p>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-slate-500">
                        <tr>
                            <th class="py-2 pr-4">Publisher</th>
                            <th class="py-2 pr-4">Script</th>
                            <th class="py-2 pr-4">Certified at</th>
                            <th class="py-2 pr-4">Basis</th>
                            <th class="py-2">Figures</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($certification as $row)
                            <tr class="border-t border-slate-100 dark:border-slate-800">
                                <td class="py-2 pr-4">{{ $row['group'] }}</td>
                                <td class="py-2 pr-4">{{ $row['script'] }}</td>
                                <td class="py-2 pr-4">&alpha; = {{ $row['alpha'] }}</td>
                                <td class="py-2 pr-4">
                                    @if ($row['conditional'])
                                        conditional on this group
                                    @else
                                        borrowed from a wider pool ({{ $row['basis'] }}) &mdash; not a claim about this group alone
                                    @endif
                                </td>
                                <td class="py-2">{{ number_format($row['figures']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <form method="GET" class="grid gap-3 md:grid-cols-4">
            <input name="q" value="{{ $filters['q'] ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950" placeholder="Search by filename or URL">
            <select name="publisher" class="rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                <option value="">All publishers</option>
                @foreach ($publishers as $publisher)
                    <option value="{{ $publisher }}" @selected(($filters['publisher'] ?? null) === $publisher)>{{ $publisher }}</option>
                @endforeach
            </select>
            <select name="kind" class="rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                <option value="documents" @selected(($filters['kind'] ?? 'documents') === 'documents')>Documents</option>
                <option value="archived" @selected(($filters['kind'] ?? null) === 'archived')>Archived web pages and records</option>
                <option value="all" @selected(($filters['kind'] ?? null) === 'all')>Everything archived</option>
            </select>
            <button class="rounded-lg bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950">Search</button>
        </form>

        <div class="space-y-3">
            @forelse ($documents as $document)
                <article class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <a class="font-semibold" href="{{ route('public.evidence.show', $document) }}">
                        {{ $document->original_filename ?: $document->source_url }}
                    </a>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $document->publisher_name }} ·
                        {{ $document->media_type }} ·
                        {{ $document->page_count }} pages ·
                        <span class="font-medium">{{ number_format($document->figure_count) }} certified figures</span>
                    </p>
                </article>
            @empty
                <p class="text-slate-500">Nothing in this snapshot matches the current filters.</p>
            @endforelse
        </div>

        <p class="max-w-3xl text-sm text-slate-500">
            These are scanned pages of audit reports, and audit reports name people. If something
            here is wrong, or should not be published at all,
            <a class="underline" href="{{ route('public.corrections.create') }}">say so</a> — every
            request is recorded with what was decided and why, including the ones we refuse.
        </p>

        {{ $documents->links() }}
    </section>
</x-layouts.app>
