{{--
    Shown on every public page served from the domain tables.

    Those tables hold seeded rows — "Mirpur Water Resilience Package" under a
    "Ministry of Planning" — written to develop against. They are plausible
    Bangladeshi records with no marking, and on a site about Bangladeshi public
    spending a reader has no way to tell them from the real thing. Publishing
    them unmarked would be publishing fabricated government records, which is
    the one failure this project could not come back from.

    The notice goes above the content rather than in a footer, and says what the
    rows are rather than hedging about "sample data".
--}}
<div role="alert" class="mb-6 rounded-lg border-2 border-amber-500 bg-amber-50 p-4 text-amber-950 dark:border-amber-500 dark:bg-amber-950/40 dark:text-amber-100">
    <p class="font-semibold">Nothing on this page is a real record.</p>
    <p class="mt-1 text-sm">
        These entries were written to develop the software against. They are invented — no project,
        agency, tender, contractor or document here corresponds to anything published by any
        Bangladeshi public body, and no figure on this page was read from any document.
    </p>
    <p class="mt-2 text-sm">
        The records CivicLens has actually read and certified are at
        <a class="font-semibold underline" href="{{ route('public.evidence.index') }}">certified evidence</a>,
        where every figure carries the document and page it came from.
    </p>
</div>
