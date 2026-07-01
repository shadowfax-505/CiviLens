<x-layouts.app :title="$report->title">
    <section class="space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase text-emerald-700">{{ $report->public_uuid }}</p>
            <h1 class="mt-2 text-3xl font-bold">{{ $report->title }}</h1>
            <p class="mt-2 text-slate-600 dark:text-slate-300">{{ $report->description }}</p>
        </div>
        <form method="POST" action="{{ route('admin.citizen-reports.status', $report) }}" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
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
        <section class="space-y-3">
            <h2 class="text-xl font-semibold">Activity</h2>
            @foreach ($report->activities as $activity)
                <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-900">{{ str($activity->event)->headline() }} · {{ $activity->created_at->toDayDateTimeString() }}</div>
            @endforeach
        </section>
    </section>
</x-layouts.app>
