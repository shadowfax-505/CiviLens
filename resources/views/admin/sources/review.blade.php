<x-layouts.app title="Review queue - CivicLens">
    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Governed acquisition</p>
            <h1 class="text-3xl font-bold">Was this read correctly?</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-600 dark:text-slate-300">
                One value at a time, drawn at random. Your judgement is the label the risk bound is computed from,
                so an honest "unsure" is worth more than a guess — a coerced label would be treated downstream as
                though someone had actually known.
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
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Needed per group</p>
            <p class="mt-1 text-3xl font-black">{{ $progress['minimum'] }}</p>
            <p class="text-xs text-slate-500">below this a group is certified for nothing</p>
        </div>
    </section>

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
                <span class="cl-chip">{{ str($item->field_key)->headline() }}</span>
                <span>{{ $item->publisher_group }}</span>
                <span>·</span>
                <span>{{ $item->script_class }}</span>
                <span>·</span>
                <span>page {{ $item->evidence_page_number }}</span>
            </div>

            <p class="mt-4 text-sm font-semibold text-slate-500">The system read</p>
            <p class="mt-1 break-all font-mono text-4xl font-black">{{ $item->extracted_value }}</p>

            @php
                $text = (string) ($item->page?->extracted_text ?? '');
                $start = max(0, (int) $item->evidence_offset_start - 220);
                $length = ((int) $item->evidence_offset_end - (int) $item->evidence_offset_start) + 440;
            @endphp

            @if ($text !== '')
                <p class="mt-6 text-sm font-semibold text-slate-500">Where it came from</p>
                <p class="mt-1 whitespace-pre-wrap break-words rounded bg-slate-50 p-4 text-sm leading-relaxed dark:bg-slate-800">{{ mb_substr($text, $start, $length) }}</p>
            @endif

            <form method="POST" action="{{ route('admin.sources.review.store', $item) }}" class="mt-6" x-ref="form">
                @csrf
                <input type="hidden" name="verdict" x-ref="verdict" value="correct">
                <label class="text-sm font-semibold">Note, if anything needs saying
                    <input class="mt-1 w-full rounded border px-3 py-2 text-slate-950" name="note" maxlength="500">
                </label>
                <div class="mt-4 flex flex-wrap gap-3">
                    <button class="cl-button-primary" type="button" @click="submit('correct')">Correct <span class="opacity-60">(c)</span></button>
                    <button class="cl-button" type="button" @click="submit('incorrect')">Incorrect <span class="opacity-60">(x)</span></button>
                    <button class="cl-button" type="button" @click="submit('unsure')">Unsure <span class="opacity-60">(u)</span></button>
                </div>
            </form>
        </section>
    @endif
</x-layouts.app>
