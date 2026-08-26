<x-layouts.app :title="$document->original_filename ?: 'Document'">
    <section class="space-y-6">
        <header class="space-y-2">
            <h1 class="text-2xl font-bold">{{ $document->original_filename ?: $document->source_url }}</h1>
            <p class="text-sm text-slate-500">
                {{ $document->publisher_name }} ·
                {{ $document->page_count }} pages ·
                retrieved {{ $document->retrieved_at?->toDayDateTimeString() }}
            </p>
            <p class="break-all text-sm">
                <a class="underline" href="{{ $document->source_url }}" rel="nofollow noopener">{{ $document->source_url }}</a>
            </p>
            <p class="font-mono text-xs text-slate-500">sha256 {{ $document->sha256 }}</p>
            @if ($document->attribution_name)
                <p class="text-sm text-slate-500">Attribution: {{ $document->attribution_name }}@if ($document->rights_decision) · {{ $document->rights_decision }}@endif</p>
            @endif
        </header>

        @if ($figures->isEmpty())
            <p class="rounded-lg border border-slate-200 bg-white p-5 text-slate-500 dark:border-slate-800 dark:bg-slate-900">
                Nothing in this item was certified. It is listed because it was archived, not because
                a value was read from it.
            </p>
        @else
            <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <table class="w-full text-left text-sm">
                    <thead class="text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Page</th>
                            <th class="px-4 py-3">Kind</th>
                            <th class="px-4 py-3">Value as printed</th>
                            <th class="px-4 py-3">Normalised</th>
                            <th class="px-4 py-3">Certified at</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($figures as $figure)
                            <tr class="border-t border-slate-100 dark:border-slate-800 align-top">
                                <td class="px-4 py-2">
                                    {{ $figure->page_number }}
                                    {{-- What makes the number checkable by someone who does not
                                         trust it: a page number is a reference, the page with the
                                         characters ringed is evidence. --}}
                                    <a class="mt-1 block text-xs underline"
                                       href="{{ route('public.evidence.figure.page', $figure) }}"
                                       target="_blank" rel="noopener">see it on the page</a>
                                </td>
                                <td class="px-4 py-2">{{ $figure->field_kind }}</td>
                                <td class="px-4 py-2">
                                    <span class="font-mono">{{ $figure->extracted_value }}</span>
                                    <img class="mt-2 max-w-xs rounded border border-slate-200 dark:border-slate-700"
                                         loading="lazy"
                                         src="{{ route('public.evidence.figure.page', [$figure, 'crop' => 1]) }}"
                                         alt="The characters this value was read from, cropped from page {{ $figure->page_number }}">
                                </td>
                                <td class="px-4 py-2 font-mono text-slate-500">{{ $figure->normalized_value }}</td>
                                <td class="px-4 py-2">
                                    &alpha; = {{ $figure->certified_alpha }}
                                    <span class="text-slate-500">
                                        @if ($figure->isConditional())
                                            (conditional on {{ $figure->publisher_group }} · {{ $figure->script_class }})
                                        @else
                                            (borrowed from a wider pool)
                                        @endif
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <p class="max-w-3xl text-sm text-slate-500">
            Every figure below is shown as it appears on the scanned page it was read from. If a
            crop looks wrong, it is wrong, and that is the point of showing it: nothing here asks to
            be taken on trust.
        </p>

        <p class="max-w-3xl text-sm text-slate-500">
            The guarantee is about the reading, not the meaning. A value certified as an amount is a
            number the system is confident it read off the page correctly; whether that number is a
            sum of money, a percentage, or the number of a section is not something it decides.
            <span class="font-mono">৩.১</span> beginning
            <span class="font-mono">৩.১ ভূমিকা:</span> is a heading, and it is certified as read.
        </p>

        <p class="max-w-3xl text-sm text-slate-500">
            These are scanned pages of audit reports, and audit reports name people. If something
            here is wrong, or should not be published at all,
            <a class="underline" href="{{ route('public.corrections.create') }}">say so</a> — every
            request is recorded with what was decided and why, including the ones we refuse.
        </p>

        <p class="text-sm text-slate-500">
            From snapshot <span class="font-mono">{{ $snapshot->label }}</span>.
        </p>
    </section>
</x-layouts.app>
