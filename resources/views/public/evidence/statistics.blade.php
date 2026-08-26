<x-layouts.app title="What this corpus contains">
    <section class="space-y-8">
        <header class="space-y-3">
            <h1 class="text-3xl font-bold">What this corpus contains</h1>
            @if ($snapshot === null)
                <p class="text-slate-500">Nothing has been published yet.</p>
            @else
                <p class="max-w-3xl text-slate-600 dark:text-slate-400">
                    Counts from snapshot <span class="font-mono">{{ $snapshot }}</span>. Every count is
                    shown beside what was withheld, because the figures published here are the part the
                    system was willing to stand behind rather than the contents of these documents.
                </p>
            @endif
        </header>

        @if ($snapshot !== null)
            <section class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Published</p>
                    <p class="mt-1 text-3xl font-black">{{ number_format($published) }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Withheld</p>
                    <p class="mt-1 text-3xl font-black">{{ number_format($withheld) }}</p>
                </div>
                <div class="rounded-lg border-2 border-amber-500 bg-amber-50 p-5 text-amber-950 dark:bg-amber-950/40 dark:text-amber-100">
                    <p class="text-xs font-semibold uppercase tracking-wide">Share withheld</p>
                    <p class="mt-1 text-3xl font-black">{{ $share_withheld === null ? '—' : number_format($share_withheld * 100, 1).'%' }}</p>
                </div>
            </section>

            {{-- The refusal, stated where a reader would look for the number it refuses. --}}
            <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-semibold">There is no total here, and there will not be one</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $why_there_are_no_totals }}</p>
            </section>

            @if ($error_rate_among_judged)
                <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-semibold">How often a person found a reading wrong</h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                        {{ number_format($error_rate_among_judged['rate'] * 100, 1) }}% of
                        {{ number_format($judged_by_a_person) }} values judged by a person were marked wrong,
                        and the exact interval around that rate runs from
                        {{ number_format($error_rate_among_judged['interval']['low'] * 100, 1) }}% to
                        {{ number_format($error_rate_among_judged['interval']['high'] * 100, 1) }}%.
                        At this sample size the range is wide, and quoting the rate without it would claim
                        a precision these judgements cannot support.
                    </p>
                </section>
            @endif

            <section>
                <h2 class="font-semibold">By publisher</h2>
                <div class="mt-3 overflow-x-auto rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <table class="w-full text-left text-sm">
                        <thead class="text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Publisher</th>
                                <th class="px-4 py-3">Documents</th>
                                <th class="px-4 py-3">Figures published</th>
                                <th class="px-4 py-3">Withheld</th>
                                <th class="px-4 py-3">Share withheld</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($publishers as $row)
                                <tr class="border-t border-slate-100 dark:border-slate-800">
                                    <td class="px-4 py-2">{{ $row['publisher'] }}</td>
                                    <td class="px-4 py-2">{{ number_format($row['documents']) }}</td>
                                    <td class="px-4 py-2">{{ number_format($row['figures_published']) }}</td>
                                    <td class="px-4 py-2">{{ number_format($row['figures_withheld']) }}</td>
                                    <td class="px-4 py-2">
                                        @if ($row['share_withheld'] === null)
                                            —
                                        @elseif ($row['share_withheld'] >= 1.0)
                                            <span class="font-semibold">everything</span>
                                            <span class="text-slate-500">— nothing from this publisher is certified yet</span>
                                        @else
                                            {{ number_format($row['share_withheld'] * 100, 1) }}%
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </section>
</x-layouts.app>
