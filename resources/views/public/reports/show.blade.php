<x-layouts.app :title="$report->title">
    <section class="space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase text-emerald-700">Tracking {{ $report->public_uuid }}</p>
            <h1 class="mt-2 text-3xl font-bold">{{ $report->title }}</h1>
            <p class="mt-2 text-slate-600 dark:text-slate-300">Report details are visible to the submitter and authorized moderators.</p>
        </div>
        <dl class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><dt class="text-xs text-slate-500">Category</dt><dd>{{ $report->category?->name }}</dd></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><dt class="text-xs text-slate-500">Status</dt><dd>{{ $report->status?->name }}</dd></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><dt class="text-xs text-slate-500">Submitted</dt><dd>{{ $report->submitted_at?->toFormattedDateString() }}</dd></div>
        </dl>

        <section class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-xl font-semibold">Submission Details</h2>
                <dl class="mt-4 grid gap-3 text-sm">
                    <div><dt class="text-slate-500">Description</dt><dd class="mt-1 whitespace-pre-line">{{ $report->description }}</dd></div>
                    <div><dt class="text-slate-500">Location</dt><dd>{{ $report->location_text ?? 'Not provided' }}</dd></div>
                    <div><dt class="text-slate-500">Contact preference</dt><dd>{{ str($report->contact_preference)->headline() }}</dd></div>
                    <div><dt class="text-slate-500">Project</dt><dd>{{ $report->project?->name ?? 'Not linked' }}</dd></div>
                    <div><dt class="text-slate-500">Agency</dt><dd>{{ $report->agency?->name ?? 'Not linked' }}</dd></div>
                    <div><dt class="text-slate-500">Document</dt><dd>{{ $report->document?->title ?? 'Not linked' }}</dd></div>
                    <div><dt class="text-slate-500">Geography</dt><dd>{{ collect([$report->ward?->name, $report->union?->name, $report->upazila?->name, $report->district?->name, $report->division?->name, $report->country?->name])->filter()->join(', ') ?: 'Not linked' }}</dd></div>
                </dl>
            </div>

            @if ($report->attachment_path)
                <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-xl font-semibold">Supporting File</h2>
                    <a class="mt-4 inline-flex rounded border px-3 py-2 text-sm font-semibold dark:border-slate-700" href="{{ \Illuminate\Support\Facades\Storage::disk($report->attachment_disk ?? 'public')->url($report->attachment_path) }}" target="_blank" rel="noopener">
                        {{ $report->attachment_original_filename ?? 'Download attachment' }}
                    </a>
                    <p class="mt-3 text-sm text-slate-500">{{ $report->attachment_mime_type ?? 'Uploaded file' }}</p>
                </div>
            @endif
        </section>

        @if ($report->attachment_path)
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs uppercase text-slate-500">Supporting files</p>
                <a class="mt-2 inline-flex rounded border px-3 py-2 text-sm font-semibold dark:border-slate-700" href="{{ \Illuminate\Support\Facades\Storage::disk($report->attachment_disk ?? 'public')->url($report->attachment_path) }}" target="_blank" rel="noopener">
                    {{ $report->attachment_original_filename ?? 'Download attachment' }}
                </a>
            </div>
        @endif
    </section>
</x-layouts.app>
