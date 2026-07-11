<x-layouts.app title="My Reports">
    <section class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-3xl font-bold">My Reports</h1>
            <a class="cl-button-primary" href="{{ route('public.reports.create') }}">Submit New Report</a>
        </div>
        @forelse ($reports as $report)
            <a class="block rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900 {{ (string) request('submitted') === (string) $report->id ? 'ring-2 ring-emerald-500' : '' }}" href="{{ route('citizen.reports.show', $report) }}">
                <span class="font-semibold">{{ $report->title }}</span>
                <span class="ml-2 text-sm text-slate-500">{{ $report->status?->name }}</span>
                @if ((string) request('submitted') === (string) $report->id)<span class="ml-2 text-sm font-semibold text-emerald-700 dark:text-emerald-300">Newly submitted</span>@endif
            </a>
        @empty
            <p class="text-slate-500">You have not submitted any reports.</p>
        @endforelse
        {{ $reports->links() }}
    </section>
</x-layouts.app>
