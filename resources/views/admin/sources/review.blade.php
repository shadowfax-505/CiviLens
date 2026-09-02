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

    {{--
        Drawing uniformly over the whole corpus makes a rare publisher
        unreachable: one holding 69 of 17,773 unlabelled candidates needs about
        5,000 judgements before its own 19 turn up. Choosing the group keeps the
        draw uniform inside it, which is what the bound requires, and is the only
        way the group-conditional scheme is usable on the publishers it exists
        for.
    --}}
    <section class="mt-6 flex flex-wrap items-center gap-2" aria-label="Which group to judge">
        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Judging</span>

        <a class="cl-chip @if ($group === null) ring-2 ring-slate-900 dark:ring-white @endif"
           href="{{ route('admin.sources.review', ['group' => '']) }}">
            Everything ({{ number_format(collect($groups)->sum('waiting')) }})
        </a>

        {{-- Ordered by what finishing each one costs, so the group a reviewer can
             complete soonest is the first one they see. A chip reading the size of
             the pile put the largest group first, which is the opposite. --}}
        @foreach ($groups as $level)
            @php($done = $level['remaining_effort'] === 0)
            <a class="cl-chip @if ($group === $level['key']) ring-2 ring-slate-900 dark:ring-white @endif @if ($done) opacity-50 @endif"
               href="{{ route('admin.sources.review', ['group' => $level['key']]) }}">
                {{ $level['key'] }}
                @if ($level['waiting'] === 0)
                    <span class="opacity-60">· nothing left</span>
                @elseif ($level['to_target'] === 0)
                    <span class="opacity-60">· done</span>
                @elseif ($level['to_target'] > $level['waiting'])
                    <span class="font-semibold">· all {{ number_format($level['waiting']) }}</span>
                @else
                    <span class="font-semibold">· {{ $level['to_target'] }} more</span>
                @endif
            </a>
        @endforeach
    </section>

    @if ($group !== null)
        @php($current = collect($groups)->firstWhere('key', $group))
        <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">
            Drawing only from <strong>{{ $group }}</strong>. Values are still picked at random within it, which is what
            the guarantee requires; the guarantee itself is fitted per publisher, script and kind of value, so judging
            one stratum at a time is how a rare publisher &mdash; or a kind of value nobody has judged yet &mdash;
            reaches a level of its own.
            @if ($current)
                @if ($current['to_target'] === 0)
                    <strong>This group has reached &alpha; = 0.05.</strong> Further labels tighten the estimate rather
                    than the level.
                @elseif ($current['to_target'] > $current['waiting'])
                    It holds {{ number_format($current['waiting']) }} candidates against the
                    {{ $current['to_target'] }} the target needs, so judging every one of them reaches
                    &alpha; = {{ number_format($current['best_possible_alpha'], 3) }} &mdash; a real level, and not nothing.
                @else
                    <strong>{{ $current['to_target'] }} more</strong> reaches &alpha; = 0.05 for this group.
                @endif
            @endif
        </p>
    @endif

    {{-- Auditing what was already certified, rather than adding to what will be.
         2,394 published figures were read at a token confidence below 80 and
         seven of them have ever been checked. --}}
    <section class="mt-4 flex flex-wrap items-center gap-2" aria-label="What to judge">
        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Mode</span>
        <a class="cl-chip @if (! $audit && ! $second_opinion) ring-2 ring-slate-900 dark:ring-white @endif"
           href="{{ route('admin.sources.review') }}">New values</a>
        <a class="cl-chip @if ($second_opinion) ring-2 ring-slate-900 dark:ring-white @endif"
           href="{{ route('admin.sources.review', ['second' => 1]) }}">Second opinion</a>
        <a class="cl-chip @if ($audit) ring-2 ring-slate-900 dark:ring-white @endif"
           href="{{ route('admin.sources.review', ['audit' => 1]) }}">Audit certified poor reads</a>
    </section>

    @if ($audit)
        <p class="mt-3 max-w-3xl rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200">
            <strong>Auditing values that are already published.</strong> These were read at a token
            confidence below the acceptance threshold and certified anyway, because the second read
            agreed with the first. Seven such values have ever been checked and none was wrong, which
            sounds reassuring and is not: the interval around nought-of-seven runs to 41%.
            <br>
            These judgements are kept out of the calibration set and out of the held-out sample, because
            a targeted draw cannot stand in for a uniform one. They measure the error rate inside this
            stratum, which is the question worth asking.
        </p>
    @endif

    {{-- The whole point of the group chips: without a specific group the draw is
         uniform over the corpus, and a group holding 40 of 24,000 candidates is
         unreachable in practice rather than in principle. --}}
    @php($next = collect($groups)->first(fn ($level) => $level['remaining_effort'] > 0))
    @if ($next && $group !== $next['key'])
        <p class="mt-3 text-sm">
            <a class="cl-button-primary inline-block" href="{{ route('admin.sources.review', ['group' => $next['key']]) }}">
                Judge {{ $next['key'] }} next &mdash;
                @if ($next['to_target'] > $next['waiting'])
                    all {{ number_format($next['waiting']) }} of them
                @else
                    {{ $next['to_target'] }} to go
                @endif
            </a>
            <span class="ml-2 text-slate-500">the group closest to a level of its own</span>
        </p>
    @endif

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
            <p class="mt-1 text-xs text-slate-500">
                <strong>Judged and counted are different numbers.</strong> Roughly three judgements
                in ten are held out to measure the error rate rather than to fit the threshold, and a
                value the pipeline could not score &mdash; one it could not find again on its page,
                or deferred for a disputed sign, alphabet or leading digit &mdash; cannot be part of
                a conformal calibration set at all. Those are not lost: almost all of them are
                waiting on <code>civiclens:score-fields</code>, not on more judgements.
                Groups with no labels are listed too. They certify nothing, which is the reason to
                judge them: a group needs only nineteen values to reach &alpha; = 0.05, and reaching
                it is what lets every other value in that group be decided by a threshold rather
                than deferred by rule.
            </p>
            <div class="mt-2 overflow-x-auto">
                <table class="w-full min-w-[28rem] text-left text-sm">
                    <thead>
                        <tr class="text-xs uppercase tracking-wide text-slate-500">
                            <th class="py-1 pr-4">Group</th>
                            <th class="py-1 pr-4">You judged</th>
                            <th class="py-1 pr-4">Counted</th>
                            <th class="py-1 pr-4">To reach &alpha; = 0.05</th>
                            <th class="py-1 pr-4">Tightest &alpha;</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($progress['levels'] as $level)
                            <tr class="border-t border-slate-100 dark:border-slate-800">
                                <td class="py-1 pr-4 font-mono text-xs">{{ $level['key'] }}</td>
                                <td class="py-1 pr-4">{{ number_format($level['judged']) }}</td>
                                {{-- Judged and counted are different numbers, and a screen that
                                     showed only the second read as though the work had not
                                     happened. Some judgements are held out to measure error rather
                                     than to fit the threshold; a value with no score cannot be in a
                                     conformal calibration set at all, and almost every one of those
                                     is waiting on a re-score rather than on more judgements. --}}
                                <td class="py-1 pr-4">
                                    <strong>{{ $level['size'] }}</strong>
                                    @if ($level['held_out'] > 0)
                                        <span class="block text-xs text-slate-500">
                                            {{ number_format($level['held_out']) }} held out to measure error
                                        </span>
                                    @endif
                                    @if ($level['unscorable'] > 0)
                                        <span class="block text-xs text-amber-700 dark:text-amber-400">
                                            {{ number_format($level['unscorable']) }} awaiting a re-score
                                        </span>
                                    @endif
                                </td>
                                {{-- The finish line, not the size of the pile. A group showing
                                     3,353 waiting reads as endless work when it is sixteen
                                     judgements from certifying anything at all. --}}
                                <td class="py-1 pr-4">
                                    @if ($level['to_target'] === 0)
                                        <span class="text-emerald-700 dark:text-emerald-400">reached</span>
                                    @elseif ($level['waiting'] === 0)
                                        <span class="text-slate-500">nothing left to judge</span>
                                    @elseif ($level['to_target'] > $level['waiting'])
                                        {{-- A finish line that does not exist is worse than none.
                                             This group holds fewer candidates than the target needs,
                                             so judging every one of them still lands at a looser
                                             level — which is a real level, not nothing. --}}
                                        <span class="text-amber-700 dark:text-amber-400">out of reach</span>
                                        <span class="text-xs text-slate-500">
                                            &mdash; only {{ number_format($level['waiting']) }} exist; judging all of them
                                            reaches &alpha; = {{ number_format($level['best_possible_alpha'], 3) }}
                                        </span>
                                    @else
                                        <strong>{{ $level['to_target'] }}</strong> more
                                        <span class="text-xs text-slate-500">of {{ number_format($level['waiting']) }} waiting</span>
                                    @endif
                                </td>
                                <td class="py-1 pr-4">
                                    @if ($level['size'] === 0)
                                        <span class="text-slate-500">nothing yet</span>
                                    @else
                                        {{ number_format($level['alpha'], 3) }}
                                        @unless ($level['at_target'])
                                            <span class="text-xs text-slate-500">&mdash; looser than the target</span>
                                        @endunless
                                    @endif
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
                {{-- Carries the mode through the post, so an audit judgement is
                     recorded as one and stays out of the calibration set. --}}
                @if ($audit)
                    <input type="hidden" name="audit" value="1">
                @endif
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
