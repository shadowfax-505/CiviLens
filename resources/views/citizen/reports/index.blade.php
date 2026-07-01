<x-layouts.app title="My Reports">
    <section class="space-y-6">
        <h1 class="text-3xl font-bold">My Reports</h1>
        @forelse ($reports as $report)
            <a class="block rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" href="{{ route('citizen.reports.show', $report) }}">
                <span class="font-semibold">{{ $report->title }}</span>
                <span class="ml-2 text-sm text-slate-500">{{ $report->status?->name }}</span>
            </a>
        @empty
            <p class="text-slate-500">You have not submitted any reports.</p>
        @endforelse
        {{ $reports->links() }}
    </section>
</x-layouts.app>
