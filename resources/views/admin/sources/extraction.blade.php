<x-layouts.app title="Extraction overview - CivicLens">
    @php
        $abstained = (int) ($summary['pages_by_path']['abstained'] ?? 0);
        $total = (int) ($summary['total_pages'] ?? 0);
    @endphp

    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Governed acquisition</p>
            <h1 class="text-3xl font-bold">What the pipeline could read</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-600 dark:text-slate-300">
                A page counted as extracted is not the same as a page that was read. Pages the recognizer would not
                vouch for are shown here as abstentions rather than folded into a total, because the number that
                matters when planning any analysis is how much text the system actually has.
            </p>
        </div>
        <a class="cl-chip" href="{{ route('admin.sources.index') }}">Back to registry</a>
    </div>

    @if ($total === 0)
        <div class="cl-card mt-8 p-6">
            <h2 class="text-lg font-black">Nothing extracted yet</h2>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                No artifact has been read. Documents are extracted after they are fetched and scanned; an artifact
                that could not be scanned stays quarantined and is never parsed.
            </p>
        </div>
    @else
        {{-- The thresholds were computed for weeks before anything applied them.
         A screen that reports what was read but never what was certified hides
         exactly that. --}}
    <section class="mt-6 grid gap-3 sm:grid-cols-4" aria-label="What the certification decided">
        <div class="cl-card p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Auto-accepted</p>
            <p class="mt-1 text-3xl font-black">{{ number_format($decisions['accepted']) }}</p>
            <p class="text-xs text-slate-500">below their group's certified threshold</p>
        </div>
        <div class="cl-card p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Deferred</p>
            <p class="mt-1 text-3xl font-black">{{ number_format($decisions['deferred']) }}</p>
            <p class="text-xs text-slate-500">no threshold, no score, or above it</p>
        </div>
        <div class="cl-card p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Not yet decided</p>
            <p class="mt-1 text-3xl font-black">{{ number_format($decisions['pending']) }}</p>
        </div>
        <div class="cl-card p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">On a borrowed threshold</p>
            <p class="mt-1 text-3xl font-black">{{ number_format($decisions['borrowed']) }}</p>
            <p class="text-xs text-slate-500">certified for a wider population, not for this group</p>
        </div>
    </section>

    <section class="mt-8 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Extraction totals">
            <div class="cl-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pages</p>
                <p class="mt-1 text-3xl font-black">{{ number_format($total) }}</p>
                <p class="text-xs text-slate-500">{{ number_format((int) ($summary['failed_runs'] ?? 0)) }} failed runs</p>
            </div>
            <div class="cl-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Read from a text layer</p>
                <p class="mt-1 text-3xl font-black">{{ number_format((int) ($summary['native_pages'] ?? 0)) }}</p>
                <p class="text-xs text-slate-500">
                    born-digital share {{ $summary['born_digital_share'] === null ? '—' : number_format((float) $summary['born_digital_share'] * 100, 1).'%' }}
                </p>
            </div>
            <div class="cl-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sent to OCR</p>
                <p class="mt-1 text-3xl font-black">{{ number_format((int) ($summary['ocr_required_pages'] ?? 0)) }}</p>
                <p class="text-xs text-slate-500">accept confidence {{ $summary['accept_confidence'] }}</p>
            </div>
            <div class="cl-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Abstained</p>
                <p @class(['mt-1 text-3xl font-black', 'text-amber-700 dark:text-amber-300' => $abstained > 0])>{{ number_format($abstained) }}</p>
                <p class="text-xs text-slate-500">
                    {{ $total > 0 ? number_format($abstained / $total * 100, 1).'% of pages' : '—' }} carry no vouched text
                </p>
                {{-- A blank page and an illegible one are both abstentions and
                     mean opposite things about how well the system reads. --}}
                <p class="mt-2 text-xs text-slate-500">
                    {{ number_format((int) ($summary['abstained_unreadable_pages'] ?? 0)) }} recognised but below the bar
                </p>
                <p class="text-xs text-slate-500">
                    {{ number_format((int) ($summary['abstained_blank_pages'] ?? 0)) }} carried nothing to read
                </p>
            </div>
        </section>

        <section class="cl-card mt-8 p-6" aria-label="Pages by extraction path">
            <h2 class="text-lg font-black">How each page was read</h2>
            <p class="mt-1 text-sm text-slate-500">
                Routing threshold {{ $summary['threshold_chars_per_square_inch'] }} characters per square inch.
            </p>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[28rem] text-left text-sm">
                    <caption class="sr-only">Count of pages by extraction path</caption>
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700">
                            <th scope="col" class="py-2 pr-4 font-semibold">Path</th>
                            <th scope="col" class="py-2 pr-4 font-semibold">Pages</th>
                            <th scope="col" class="py-2 font-semibold">Share</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (($summary['pages_by_path'] ?? []) as $path => $count)
                            <tr class="border-b border-slate-100 dark:border-slate-800">
                                <td class="py-2 pr-4">{{ str($path)->headline() }}</td>
                                <td class="py-2 pr-4 font-mono">{{ number_format((int) $count) }}</td>
                                <td class="py-2">{{ $total > 0 ? number_format((int) $count / $total * 100, 1).'%' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        @if (! empty($summary['pages_by_script']))
            <section class="cl-card mt-8 p-6" aria-label="Pages by script">
                <h2 class="text-lg font-black">Script</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Bengali and English pages behave differently enough that a single accuracy figure across both
                    would describe neither.
                </p>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[28rem] text-left text-sm">
                        <caption class="sr-only">Count of pages and native share by script class</caption>
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-700">
                                <th scope="col" class="py-2 pr-4 font-semibold">Script</th>
                                <th scope="col" class="py-2 pr-4 font-semibold">Pages</th>
                                <th scope="col" class="py-2 font-semibold">Read from a text layer</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (($summary['pages_by_script'] ?? []) as $script => $count)
                                <tr class="border-b border-slate-100 dark:border-slate-800">
                                    <td class="py-2 pr-4">{{ $script }}</td>
                                    <td class="py-2 pr-4 font-mono">{{ number_format((int) $count) }}</td>
                                    <td class="py-2">
                                        {{ isset($summary['native_share_by_script'][$script]) ? number_format((float) $summary['native_share_by_script'][$script] * 100, 1).'%' : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @endif
</x-layouts.app>
