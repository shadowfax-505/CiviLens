<x-layouts.app title="Ask for a correction or withdrawal">
    <section class="mx-auto max-w-3xl space-y-6">
        <header class="space-y-3">
            <h1 class="text-3xl font-bold">Ask for a correction or withdrawal</h1>
            <p class="text-slate-600 dark:text-slate-400">
                This site republishes documents issued by Bangladeshi public bodies — audit reports,
                evaluation reports, gazettes, and judgments of the High Court Division — and shows the
                scanned pages figures were read from. Audit reports name people, usually officials
                acting in an official capacity, and some pages carry a telephone number beside a firm
                or a banking reference.
            </p>
            <p class="text-slate-600 dark:text-slate-400">
                <strong>Judgments are different and worth saying so plainly.</strong> A judgment names
                the people in the case — an accused person, a petitioner, a witness — and many of them
                are private individuals rather than anyone acting in an official capacity. The
                judgments here are published by the Supreme Court itself and are reproduced as the
                court published them, without alteration. That does not make republishing them
                neutral: a page a search engine can reach is a different kind of reach from a file on
                the court's own website, and if you are named in one you may have good reason to say
                so.
            </p>
            <p class="text-slate-600 dark:text-slate-400">
                Serving those pages as images that a search engine can reach is a different kind of
                reach from a file on a ministry's website, and that difference is something a person
                can reasonably object to. Use this to tell us.
            </p>
        </header>

        @if (session('correction_reference'))
            <div class="rounded-lg border-2 border-emerald-500 bg-emerald-50 p-4 text-emerald-950 dark:bg-emerald-950/40 dark:text-emerald-100" role="status">
                <p class="font-semibold">Received.</p>
                <p class="mt-1 text-sm">
                    Your reference is <span class="font-mono">{{ session('correction_reference') }}</span>.
                    Keep it: if you did not leave contact details, it is the only way to ask what was decided.
                </p>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-lg border-2 border-rose-500 bg-rose-50 p-4 text-rose-950 dark:bg-rose-950/40 dark:text-rose-100" role="alert">
                <ul class="list-inside list-disc text-sm">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('public.corrections.store') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-semibold" for="kind">What are you asking for?</label>
                <select id="kind" name="kind" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                    <option value="correction">Something here is wrong and should be corrected</option>
                    <option value="withdrawal">Something here should not be published at all</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold" for="about">What is it about?</label>
                <p class="text-xs text-slate-500">A link, a page number, a figure, or a description. You do not need to know how this site is organised.</p>
                <textarea id="about" name="about" rows="3" required class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950">{{ old('about') }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-semibold" for="reason">Why?</label>
                <textarea id="reason" name="reason" rows="4" required class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950">{{ old('reason') }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-semibold" for="contact">How can we reach you? <span class="font-normal text-slate-500">(optional)</span></label>
                <p class="text-xs text-slate-500">You may leave this blank. We will not ask who you are before considering what you have said.</p>
                <input id="contact" name="contact" value="{{ old('contact') }}" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950">
            </div>

            <button class="rounded-lg bg-slate-950 px-5 py-2 text-white dark:bg-white dark:text-slate-950">Send</button>
        </form>

        <p class="text-sm text-slate-500">
            Every request is recorded with what was decided and why, including the ones we refuse. A
            route that kept only the requests it agreed with could not be audited, and being
            auditable is the whole claim this project makes about itself.
        </p>
    </section>
</x-layouts.app>
