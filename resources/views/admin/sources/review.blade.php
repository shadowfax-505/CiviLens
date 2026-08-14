<x-layouts.app title="Review queue - CivicLens">
    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Governed acquisition</p>
            <h1 class="text-3xl font-bold">Do these characters match the page?</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-600 dark:text-slate-300">
                One value at a time, drawn at random. The characters it was read from are marked on the page and shown
                close up; judge <strong>only whether they were read correctly</strong> — not whether the number is
                sensible, not which column or heading it sits under, and not whether it was filed as the right kind.
                Scanned tables lose their columns when they are recognized, so a figure often arrives without the row it
                belonged to; that is expected and does not make the reading wrong. Where a value could not be marked at
                all, that page was read before word positions were stored. Your judgement is the label the risk bound is
                computed from, so an honest "can't tell" is worth more than a guess.
            </p>
        </div>
        <a class="cl-chip" href="{{ route('admin.sources.index') }}">Back to registry</a>
    </div>

    @if (session('status'))
        <div class="cl-card mt-6 border-l-4 border-emerald-500 p-4" role="status">{{ session('status') }}</div>
    @endif

    <section class="mt-6 grid gap-3 sm:grid-cols-3" aria-label="Adjudication progress">
        <div class="cl-card p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Adjudicated</p>
            <p class="mt-1 text-3xl font-black">{{ number_format($progress['adjudicated']) }}</p>
        </div>
        <div class="cl-card p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Waiting</p>
            <p class="mt-1 text-3xl font-black">{{ number_format($progress['remaining']) }}</p>
        </div>
        <div class="cl-card p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">For &alpha; = 0.05</p>
            <p class="mt-1 text-3xl font-black">{{ $progress['minimum'] }}</p>
            <p class="text-xs text-slate-500">per group; a smaller group certifies at a looser level, not at none</p>
        </div>
    </section>

    @if ($progress['levels'] !== [])
        {{-- One number per group rather than one number for all of them. A rare
             publisher will never reach nineteen, and n >= 1/alpha - 1 rearranges
             to alpha >= 1/(n+1): nine labels certify at 0.10, four at 0.20. --}}
        <section class="cl-card mt-4 p-4" aria-label="What each group can currently certify">
            <p class="text-sm font-semibold">What each group can certify as it stands</p>
            <div class="mt-2 overflow-x-auto">
                <table class="w-full min-w-[28rem] text-left text-sm">
                    <thead>
                        <tr class="text-xs uppercase tracking-wide text-slate-500">
                            <th class="py-1 pr-4">Group</th>
                            <th class="py-1 pr-4">Labels</th>
                            <th class="py-1 pr-4">Tightest &alpha;</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($progress['levels'] as $level)
                            <tr class="border-t border-slate-100 dark:border-slate-800">
                                <td class="py-1 pr-4 font-mono text-xs">{{ $level['key'] }}</td>
                                <td class="py-1 pr-4">{{ $level['size'] }}</td>
                                <td class="py-1 pr-4">
                                    {{ number_format($level['alpha'], 3) }}
                                    @unless ($level['at_target'])
                                        <span class="text-xs text-slate-500">&mdash; looser than the target</span>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-slate-500">
                Past &alpha; = {{ number_format($progress['ceiling'], 2) }} the claim stops being worth making, and the
                group borrows a threshold fitted on every publisher writing that script &mdash; which is a statement
                about the script, not about the publisher, and is recorded that way.
            </p>
        </section>
    @endif

    @if ($progress['groups'] !== [])
        <section class="cl-card mt-4 p-4" aria-label="Calibration set by group">
            <h2 class="text-sm font-black">Calibration set by group</h2>
            <ul class="mt-2 space-y-1 text-sm">
                @foreach ($progress['groups'] as $group => $count)
                    <li class="flex items-center justify-between">
                        <span class="font-mono">{{ $group }}</span>
                        <span @class(['font-semibold', 'text-emerald-700 dark:text-emerald-300' => $count >= $progress['minimum']])>
                            {{ $count }} / {{ $progress['minimum'] }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($item === null)
        <div class="cl-card mt-8 p-6">
            <h2 class="text-lg font-black">Nothing waiting</h2>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                Every candidate has been seen. Generate more with
                <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">php artisan civiclens:generate-review-candidates</code>.
            </p>
        </div>
    @else
        <section class="cl-card mt-6 p-6" aria-label="Item under review"
                 x-data="{ submit(v) { this.$refs.verdict.value = v; this.$refs.form.submit(); } }"
                 @keydown.window.c.prevent="submit('correct')"
                 @keydown.window.x.prevent="submit('incorrect')"
                 @keydown.window.u.prevent="submit('unsure')">
            <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                <span>{{ $item->publisher_group }}</span>
                <span>·</span>
                <span>{{ $item->script_class }}</span>
                <span>·</span>
                <span>page {{ $item->evidence_page_number }}</span>
                {{-- The kind is a hint about where this came from, not part of
                     the question. Miscategorised items were being read as wrong
                     reads, which is a different judgement entirely. --}}
                <span>·</span>
                <span>found as {{ $item->field_key }}</span>
                @if ($item->tableCell !== null)
                    <span>·</span>
                    <span>table row {{ $item->tableCell->row_index + 1 }}, column {{ $item->tableCell->column_index + 1 }}</span>
                @endif
            </div>

            @if ($row['cells'] !== [])
                {{-- The row a figure sat in, so it can be placed among its
                     siblings. A number without its row is correctly read and
                     unusable: nobody can say which year or line item it
                     measures. --}}
                <div class="mt-4 rounded border border-slate-200 p-4 dark:border-slate-700">
                    <p class="text-sm font-semibold text-slate-500">Its row on the page</p>
                    @if ($row['label'] !== null && $row['label'] !== '')
                        <p class="mt-1 text-sm">This row is labelled <strong>{{ $row['label'] }}</strong></p>
                    @endif
                    <div class="mt-2 overflow-x-auto">
                        <table class="w-full min-w-[24rem] text-left text-sm">
                            <caption class="sr-only">The table row containing the value under review</caption>
                            <tbody>
                                <tr>
                                    @foreach ($row['cells'] as $cell)
                                        <td @class([
                                            'border-b border-slate-100 py-2 pr-4 align-top dark:border-slate-800',
                                            'font-black' => $cell['is_value'],
                                        ])>
                                            <span class="block text-xs text-slate-500">col {{ $cell['column'] + 1 }}</span>
                                            {{ $cell['text'] !== '' ? $cell['text'] : '—' }}
                                        </td>
                                    @endforeach
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">
                        Row and label are shown as read, not as verified. They are context for placing the figure and
                        play no part in the judgement below, which is only about the characters.
                    </p>
                </div>
            @endif

            <div class="mt-4 grid gap-6 lg:grid-cols-2">
                <div>
                    <p class="text-sm font-semibold text-slate-500">The system read</p>
                    <p class="mt-1 break-all font-mono text-5xl font-black">{{ $item->extracted_value }}</p>

                    @if ($located > 0)
                        {{-- The crop, not the page, is what the question is
                             about. A page of hundreds of figures makes finding
                             the value the reviewer's job, and finding it is not
                             what is being asked. --}}
                        <p class="mt-6 text-sm font-semibold text-slate-500">Where it sits on the page</p>
                        <img src="{{ route('admin.sources.review.page', [$item, 'crop' => 1]) }}"
                             alt="Close-up of the marked region of page {{ $item->evidence_page_number }}"
                             class="mt-1 w-full rounded border border-slate-200 bg-white dark:border-slate-700"
                             loading="eager">
                        @if ($located > 1)
                            <p class="mt-2 text-xs text-slate-500">
                                These characters appear in {{ $located }} places on this page, all marked on the page
                                beside this; the close-up shows the first. Judge whether they were read correctly —
                                which of them was meant is not part of the question.
                            </p>
                        @endif

                        @if ($separator_suspect)
                            <p class="mt-3 rounded border border-amber-300 bg-amber-50 p-3 text-xs text-slate-700 dark:border-amber-700 dark:bg-amber-950 dark:text-slate-200">
                                The comma in this figure does not group thousands the way either convention does, which
                                is what a <strong>decimal point read as a comma</strong> looks like. Check the marked
                                characters: if the page shows a point, this is a misreading and does not match.
                            </p>
                        @endif

                        @if ($transliterated)
                            {{-- Stated here rather than left to each reviewer:
                                 without a rule, the same item gets judged both
                                 ways and the calibration set means nothing. --}}
                            <p class="mt-3 rounded border border-amber-300 bg-amber-50 p-3 text-xs text-slate-700 dark:border-amber-700 dark:bg-amber-950 dark:text-slate-200">
                                This document stores Bengali numerals as the Latin characters that render them, so
                                <strong>১০০.০০</strong> arrives as <strong>100.00</strong>. Judge the digits, not the script:
                                same digits in the same order is a match, and a different digit anywhere is not.
                            </p>
                        @endif
                    @else
                        <p class="mt-6 text-sm font-semibold text-slate-500">Not pinpointed</p>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                            This page was read before word positions were stored, so the value cannot be marked on it.
                            Find it on the page beside this, or mark can't tell.
                        </p>
                    @endif
                </div>

                <div>
                    <p class="text-sm font-semibold text-slate-500">The whole page</p>
                    {{-- Kept beside the crop so a reviewer can see the value in
                         its place on the document, and see that the document is
                         the one it claims to be. --}}
                    <img src="{{ route('admin.sources.review.page', $item) }}"
                         alt="Scanned page {{ $item->evidence_page_number }} containing the value under review"
                         class="mt-1 w-full rounded border border-slate-200 bg-white dark:border-slate-700"
                         loading="eager">
                    <p class="mt-2 text-xs text-slate-500">
                        If the page does not load, the source document is unavailable — mark unsure and move on.
                    </p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.sources.review.store', $item) }}" class="mt-6" x-ref="form">
                @csrf
                <input type="hidden" name="verdict" x-ref="verdict" value="correct">
                <label class="text-sm font-semibold">Note, if anything needs saying
                    <input class="mt-1 w-full rounded border px-3 py-2 text-slate-950" name="note" maxlength="500">
                </label>
                <div class="mt-4 flex flex-wrap gap-3">
                    <button class="cl-button-primary" type="button" @click="submit('correct')">Matches the page <span class="opacity-60">(c)</span></button>
                    <button class="cl-button" type="button" @click="submit('incorrect')">Does not match <span class="opacity-60">(x)</span></button>
                    <button class="cl-button" type="button" @click="submit('unsure')">Can't tell <span class="opacity-60">(u)</span></button>
                </div>
                <p class="mt-2 text-xs text-slate-500">
                    Can't tell is a real answer. It records that you looked and leaves the item out of the
                    calibration set rather than putting a guess into it.
                </p>
            </form>
        </section>
    @endif
</x-layouts.app>
