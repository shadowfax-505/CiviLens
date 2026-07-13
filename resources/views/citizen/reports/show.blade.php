<x-layouts.app :title="$report->title">
    <section class="space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase text-emerald-700">Tracking {{ $report->public_uuid }}</p>
            <h1 class="mt-2 text-3xl font-bold">{{ $report->title }}</h1>
            <p class="mt-2 text-slate-600 dark:text-slate-300">Track the progress of your submitted report.</p>
        </div>

        <dl class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><dt class="text-xs text-slate-500">Category</dt><dd class="mt-1 font-medium">{{ $report->category?->name }}</dd></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><dt class="text-xs text-slate-500">Current status</dt><dd class="mt-1 font-medium">{{ $report->status?->name }}</dd></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><dt class="text-xs text-slate-500">Submitted</dt><dd class="mt-1 font-medium">{{ $report->submitted_at?->toDayDateTimeString() }}</dd></div>
        </dl>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-xl font-semibold">Submission Details</h2>
                <dl class="mt-4 grid gap-4 text-sm">
                    <div><dt class="text-slate-500">Description</dt><dd class="mt-1 whitespace-pre-line">{{ $report->description }}</dd></div>
                    <div><dt class="text-slate-500">Location</dt><dd class="mt-1">{{ $report->location_text ?? 'Not provided' }}</dd></div>
                    <div><dt class="text-slate-500">Contact preference</dt><dd class="mt-1">{{ str($report->contact_preference)->headline() }}</dd></div>
                    <div><dt class="text-slate-500">Related project</dt><dd class="mt-1">{{ $report->project?->name ?? 'Not linked' }}</dd></div>
                    <div><dt class="text-slate-500">Related agency</dt><dd class="mt-1">{{ $report->agency?->name ?? 'Not linked' }}</dd></div>
                    <div><dt class="text-slate-500">Related document</dt><dd class="mt-1">{{ $report->document?->title ?? 'Not linked' }}</dd></div>
                    <div><dt class="text-slate-500">Geography</dt><dd class="mt-1">{{ collect([$report->ward?->name, $report->union?->name, $report->upazila?->name, $report->district?->name, $report->division?->name, $report->country?->name])->filter()->join(', ') ?: 'Not linked' }}</dd></div>
                </dl>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-xl font-semibold">Supporting File</h2>
                @if ($report->attachment_path)
                    <a class="mt-4 inline-flex rounded border px-3 py-2 text-sm font-semibold dark:border-slate-700" href="{{ route('citizen.reports.attachment', $report) }}">{{ $report->attachment_original_filename ?? 'Download attachment' }}</a>
                    <p class="mt-3 text-sm text-slate-500">{{ $report->attachment_mime_type ?? 'Uploaded file' }}@if ($report->attachment_size) · {{ number_format($report->attachment_size / 1024, 1) }} KB @endif</p>
                @else
                    <p class="mt-3 text-sm text-slate-500">No supporting file was attached.</p>
                @endif
            </section>
        </div>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">Activity</h2>
            @forelse ($report->activities as $activity)
                <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="font-medium">{{ str($activity->event)->headline() }}</p>
                    <p class="mt-1 text-slate-500">{{ $activity->created_at->toDayDateTimeString() }}</p>
                </div>
            @empty
                <p class="text-sm text-slate-500">No updates have been recorded yet.</p>
            @endforelse
        </section>
    </section>
</x-layouts.app>
