<x-layouts.app title="Citizen Reports">
    <section class="space-y-6">
        <h1 class="text-3xl font-bold">Citizen Report Moderation</h1>
        @forelse ($reports as $report)
            <a class="block rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" href="{{ route('admin.citizen-reports.show', $report) }}">
                <span class="font-semibold">{{ $report->title }}</span>
                <span class="ml-2 text-sm text-slate-500">{{ $report->status?->name }} · {{ $report->submitter?->name }}</span>
            </a>
        @empty
            <p class="text-slate-500">No citizen reports are waiting for moderation.</p>
        @endforelse
        {{ $reports->links() }}
    </section>
</x-layouts.app>
