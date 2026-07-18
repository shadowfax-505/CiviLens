<x-layouts.app :title="'Change Request: ' . $changeRequest->summary">
    <section class="mx-auto max-w-5xl space-y-8">
        <div class="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-emerald-600">{{ $modules[$changeRequest->module] ?? str($changeRequest->module)->headline() }}</p>
                    <h1 class="mt-2 text-3xl font-black">{{ $changeRequest->summary }}</h1>
                    <p class="mt-2 text-slate-600 dark:text-slate-300">{{ $changeRequest->subject_label }}</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ str($changeRequest->status)->headline() }}</span>
            </div>

            <dl class="mt-6 grid gap-4 md:grid-cols-2">
                <div><dt class="text-xs uppercase tracking-[0.2em] text-slate-500">Requested by</dt><dd class="mt-1 font-semibold">{{ $changeRequest->requester?->name }}</dd></div>
                <div><dt class="text-xs uppercase tracking-[0.2em] text-slate-500">Subject</dt><dd class="mt-1 font-semibold">{{ $changeRequest->subject_type }} #{{ $changeRequest->subject_id }}</dd></div>
                <div><dt class="text-xs uppercase tracking-[0.2em] text-slate-500">Operation</dt><dd class="mt-1 font-semibold">{{ str($changeRequest->operation)->headline() }}</dd></div>
                <div><dt class="text-xs uppercase tracking-[0.2em] text-slate-500">Target field</dt><dd class="mt-1 font-semibold">{{ $changeRequest->target_field ?? '—' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-[0.2em] text-slate-500">Reviewed at</dt><dd class="mt-1 font-semibold">{{ $changeRequest->reviewed_at?->toDayDateTimeString() ?? 'Pending' }}</dd></div>
            </dl>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-black">Change Details</h2>
                <div class="mt-4 space-y-4 text-sm leading-6 text-slate-700 dark:text-slate-300">
                    <div><p class="font-semibold">Current</p><p class="whitespace-pre-wrap">{{ $changeRequest->current_value ?? '—' }}</p></div>
                    <div><p class="font-semibold">Proposed</p><p class="whitespace-pre-wrap">{{ $changeRequest->proposed_value ?? '—' }}</p></div>
                    <div><p class="font-semibold">Details</p><p class="whitespace-pre-wrap">{{ $changeRequest->details ?? '—' }}</p></div>
                    @if ($changeRequest->payload)
                        <div><p class="font-semibold">Structured payload</p><pre class="mt-1 overflow-x-auto whitespace-pre-wrap rounded bg-slate-100 p-3 text-xs dark:bg-slate-800">{{ json_encode($changeRequest->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div>
                    @endif
                    @if ($changeRequest->attachment_path)
                        <div><p class="font-semibold">Attachment</p><a class="text-emerald-700 underline dark:text-emerald-300" href="{{ route('admin.change-requests.attachment.download', $changeRequest) }}">{{ $changeRequest->attachment_name ?? 'Download attachment' }}</a></div>
                    @endif
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-black">Review</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Admins can approve, reject, or dismiss the request.</p>
                @if ($changeRequest->subject_url)
                    <a class="mt-4 inline-flex rounded-full bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-950" href="{{ $changeRequest->subject_url }}">Open Source Record</a>
                @endif

                @if ($canReview && $changeRequest->status === 'approved')
                    <form class="mt-6 space-y-3" method="POST" action="{{ route('admin.change-requests.applied', $changeRequest) }}">
                        @csrf
                        <p class="text-sm text-slate-600 dark:text-slate-300">Apply the source record through its normal admin workflow, then record that action here. This never automatically mutates source data.</p>
                        <textarea name="application_notes" class="w-full rounded-2xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-950" rows="3" placeholder="Source record update reference or notes"></textarea>
                        <button class="rounded-2xl border border-emerald-600 px-5 py-3 font-bold text-emerald-700 dark:text-emerald-300">Mark source change applied</button>
                    </form>
                @endif

                @if ($canReview)
                    <form class="mt-6 space-y-4" method="POST" action="{{ route('admin.change-requests.update', $changeRequest) }}">
                        @csrf
                        @method('PATCH')
                        <select name="status" class="w-full rounded-2xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-950">
                            @foreach (['approved', 'rejected', 'dismissed'] as $status)
                                <option value="{{ $status }}" @selected($changeRequest->status === $status)>{{ str($status)->headline() }}</option>
                            @endforeach
                        </select>
                        <textarea name="review_notes" class="w-full rounded-2xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-950" rows="4" placeholder="Review notes">{{ $changeRequest->review_notes }}</textarea>
                        <button type="submit" class="rounded-2xl bg-emerald-600 px-5 py-3 font-bold text-white hover:bg-emerald-500">Save Review</button>
                    </form>
                @endif

                @if ($changeRequest->review_notes)
                    <div class="mt-6 rounded-2xl bg-slate-100 p-4 text-sm dark:bg-slate-800">
                        <p class="font-semibold">Review notes</p>
                        <p class="mt-1 whitespace-pre-wrap">{{ $changeRequest->review_notes }}</p>
                    </div>
                @endif
            </div>
        </div>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-lg font-black">Proposal audit trail</h2>
            <div class="mt-4 space-y-3 text-sm">
                @foreach ($changeRequest->activities as $activity)
                    <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800"><strong>{{ str($activity->event)->headline() }}</strong>@if ($activity->actor) <span class="text-slate-500">by {{ $activity->actor->name }}</span>@endif @if ($activity->notes)<p class="mt-1 whitespace-pre-wrap">{{ $activity->notes }}</p>@endif <p class="mt-1 text-xs text-slate-500">{{ $activity->created_at->toDayDateTimeString() }}</p></div>
                @endforeach
            </div>
        </section>
    </section>
</x-layouts.app>
