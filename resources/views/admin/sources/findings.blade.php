<x-layouts.app title="Source findings - CivicLens">
    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Governed acquisition</p>
            <h1 class="text-3xl font-bold">What the sources have published</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-600 dark:text-slate-300">
                Every figure below is something a publisher stated about its own notices. Nothing here is inferred,
                and nothing here is a conclusion about conduct. A notice is amended or republished for many ordinary
                reasons; what matters for transparency is that the change is visible and countable.
            </p>
        </div>
        <a class="cl-chip" href="{{ route('admin.sources.index') }}">Back to registry</a>
    </div>

    @if ($rows->isEmpty())
        <div class="cl-card mt-8 p-6">
            <h2 class="text-lg font-black">Nothing observed yet</h2>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                No approved source has produced an observation. Resume an endpoint on the registry page and the
                findings will appear here once a crawl has run.
            </p>
        </div>
    @endif

    @foreach ($rows as $row)
        <section class="cl-card mt-8 p-6" aria-label="Findings for {{ $row['publisher']->name }}">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-xl font-black">{{ $row['publisher']->name }}</h2>
                    <p class="text-sm text-slate-500">{{ $row['publisher']->attribution_name }}</p>
                </div>
                <span class="cl-chip">{{ number_format($row['amendments']['notices']) }} notices observed</span>
            </div>

            <div class="mt-5 grid gap-4 sm:grid-cols-3">
                <div class="rounded border border-slate-200 p-4 dark:border-slate-700">
                    <p class="text-sm font-semibold text-slate-500">Notices declaring an amendment</p>
                    <p class="mt-1 text-3xl font-black">{{ number_format($row['amendments']['amended_notices']) }}</p>
                    <p class="text-sm text-slate-500">
                        {{ number_format($row['amendments']['amendments']) }} amendments in total
                    </p>
                </div>
                <div class="rounded border border-slate-200 p-4 dark:border-slate-700">
                    <p class="text-sm font-semibold text-slate-500">Share of notices amended</p>
                    <p class="mt-1 text-3xl font-black">
                        {{ $row['amendments']['amended_share'] === null ? '—' : number_format($row['amendments']['amended_share'] * 100, 2).'%' }}
                    </p>
                    <p class="text-sm text-slate-500">as stated by the publisher</p>
                </div>
                <div class="rounded border border-slate-200 p-4 dark:border-slate-700">
                    <p class="text-sm font-semibold text-slate-500">Notices republished with a change</p>
                    <p class="mt-1 text-3xl font-black">{{ number_format(count($row['revisions'])) }}</p>
                    <p class="text-sm text-slate-500">observed more than once</p>
                </div>
            </div>

            <p class="mt-4 text-sm text-slate-600 dark:text-slate-300">{{ $row['amendments']['statement'] }}</p>

            @if (count($row['revisions']) > 0)
                <h3 class="mt-6 text-base font-black">Recorded changes</h3>
                <ul class="mt-3 space-y-3">
                    @foreach (array_slice($row['revisions'], 0, 10) as $finding)
                        <li class="rounded border border-slate-200 p-4 text-sm dark:border-slate-700">
                            {{ $finding['statement'] ?? '' }}
                        </li>
                    @endforeach
                </ul>
                @if (count($row['revisions']) > 10)
                    <p class="mt-3 text-sm text-slate-500">
                        Showing 10 of {{ number_format(count($row['revisions'])) }} recorded changes.
                    </p>
                @endif
            @endif
        </section>
    @endforeach

    @if ($recent->isNotEmpty())
        <section class="cl-card mt-8 p-6" aria-label="Most recent observations">
            <h2 class="text-lg font-black">Most recent observations</h2>
            <p class="mt-1 text-sm text-slate-500">What each listing said at the moment it was read.</p>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[40rem] text-left text-sm">
                    <caption class="sr-only">Recent tender observations by publisher, status and time observed</caption>
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700">
                            <th scope="col" class="py-2 pr-4 font-semibold">Notice</th>
                            <th scope="col" class="py-2 pr-4 font-semibold">Publisher</th>
                            <th scope="col" class="py-2 pr-4 font-semibold">Status as stated</th>
                            <th scope="col" class="py-2 pr-4 font-semibold">Nature</th>
                            <th scope="col" class="py-2 font-semibold">Observed</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recent as $observation)
                            <tr class="border-b border-slate-100 dark:border-slate-800">
                                <td class="py-2 pr-4 font-mono">{{ $observation->external_id }}</td>
                                <td class="py-2 pr-4">{{ $observation->publisher?->name }}</td>
                                <td class="py-2 pr-4">{{ $observation->status ?: '—' }}</td>
                                <td class="py-2 pr-4">{{ $observation->procurement_nature ?: '—' }}</td>
                                <td class="py-2">{{ $observation->observed_at?->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</x-layouts.app>
