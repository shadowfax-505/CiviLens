<x-layouts.app :title="$report->title">
    <section class="space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase text-emerald-700">{{ $report->public_uuid }}</p>
            <h1 class="mt-2 text-3xl font-bold">{{ $report->title }}</h1>
            <p class="mt-2 whitespace-pre-line text-slate-600 dark:text-slate-300">{{ $report->description }}</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="space-y-6 lg:col-span-2">
                <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-xl font-semibold">Report Details</h2>
                    <dl class="mt-4 grid gap-4 text-sm md:grid-cols-2">
                        <div><dt class="text-slate-500">Category</dt><dd class="mt-1 font-medium">{{ $report->category?->name }}</dd></div>
                        <div><dt class="text-slate-500">Submitted</dt><dd class="mt-1">{{ $report->submitted_at?->toDayDateTimeString() }}</dd></div>
                        <div><dt class="text-slate-500">Submitter</dt><dd class="mt-1">{{ $report->submitter?->name ?? 'Unavailable' }} @if ($report->submitter?->email)<span class="text-slate-500">· {{ $report->submitter->email }}</span>@endif</dd></div>
                        <div><dt class="text-slate-500">Assigned to</dt><dd class="mt-1">{{ $report->assignee?->name ?? 'Unassigned' }}</dd></div>
                        <div><dt class="text-slate-500">Contact preference</dt><dd class="mt-1">{{ str($report->contact_preference)->headline() }}</dd></div>
                        <div><dt class="text-slate-500">Location</dt><dd class="mt-1">{{ $report->location_text ?? 'Not provided' }}</dd></div>
                        <div><dt class="text-slate-500">Project</dt><dd class="mt-1">{{ $report->project?->name ?? 'Not linked' }}</dd></div>
                        <div><dt class="text-slate-500">Agency</dt><dd class="mt-1">{{ $report->agency?->name ?? 'Not linked' }}</dd></div>
                        <div><dt class="text-slate-500">Document</dt><dd class="mt-1">{{ $report->document?->title ?? 'Not linked' }}</dd></div>
                        <div><dt class="text-slate-500">Geography</dt><dd class="mt-1">{{ collect([$report->ward?->name, $report->union?->name, $report->upazila?->name, $report->district?->name, $report->division?->name, $report->country?->name])->filter()->join(', ') ?: 'Not linked' }}</dd></div>
                    </dl>
                </div>

                @if ($report->attachment_path)
                    <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                        <h2 class="text-xl font-semibold">Supporting File</h2>
                        <a class="mt-4 inline-flex rounded border px-3 py-2 text-sm font-semibold dark:border-slate-700" href="{{ route('citizen.reports.attachment', $report) }}">{{ $report->attachment_original_filename ?? 'Download attachment' }}</a>
                        <p class="mt-3 text-sm text-slate-500">{{ $report->attachment_mime_type ?? 'Uploaded file' }}@if ($report->attachment_size) · {{ number_format($report->attachment_size / 1024, 1) }} KB @endif</p>
                    </div>
                @endif
            </section>

            <form method="POST" action="{{ route('admin.citizen-reports.status', $report) }}" class="h-fit space-y-4 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                @csrf
                @method('PATCH')
                <label class="block text-sm font-medium">Status
                    <select name="citizen_report_status_id" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->id }}" @selected($report->citizen_report_status_id === $status->id)>{{ $status->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-medium">Moderation Notes
                    <textarea name="moderation_notes" rows="4" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950">{{ old('moderation_notes', $report->moderation_notes) }}</textarea>
                </label>
                <button class="rounded-lg bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950">Update Status</button>
            </form>
        </div>

        <form method="POST" action="{{ route('admin.citizen-reports.acknowledgement.resend', $report) }}" class="cl-card flex flex-wrap items-center justify-between gap-4">
            @csrf
            <div>
                <h2 class="cl-card-title">Submission acknowledgement</h2>
                <p class="mt-1 text-sm cl-muted">Queues a privacy-safe receipt email to the submitting citizen. Repeat sends are limited for five minutes.</p>
            </div>
            <button class="cl-button" type="submit">Send queued-for-review email</button>
        </form>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">Activity</h2>
            @forelse ($report->activities as $activity)
                <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="font-medium">{{ str($activity->event)->headline() }} @if ($activity->actor) <span class="font-normal text-slate-500">by {{ $activity->actor->name }}</span> @endif</p>
                    @if ($activity->notes)<p class="mt-1 whitespace-pre-line">{{ $activity->notes }}</p>@endif
                    <p class="mt-1 text-slate-500">{{ $activity->created_at->toDayDateTimeString() }}</p>
                </div>
            @empty
                <p class="text-sm text-slate-500">No activity has been recorded.</p>
            @endforelse
        </section>
    </section>
</x-layouts.app>
