<x-layouts.app title="Analytics Reports">
    <section class="space-y-6">
        <div class="rounded-3xl bg-slate-950 p-8 text-white shadow-sm dark:bg-slate-900">
            <p class="text-sm font-semibold uppercase tracking-[0.24em] text-blue-300">Reporting</p>
            <h1 class="mt-3 text-4xl font-bold tracking-tight">Analytics Reports</h1>
            <p class="mt-3 max-w-3xl text-slate-300">Generated and queued analytics reports with branding, filters, KPIs, charts, and source evidence metadata.</p>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Generate Executive Summary</h2>
            <div class="mt-4 flex flex-wrap gap-3">
                @foreach (['csv' => 'CSV', 'xlsx' => 'Excel', 'pdf' => 'PDF'] as $format => $label)
                    <form method="POST" action="{{ route('admin.analytics.reports.store') }}">
                        @csrf
                        <input type="hidden" name="dashboard" value="executive">
                        <input type="hidden" name="format" value="{{ $format }}">
                        <button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 dark:bg-white dark:text-slate-950">Queue {{ $label }}</button>
                    </form>
                @endforeach
            </div>
        </section>
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/70 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Dashboard</th>
                        <th class="px-4 py-3">Format</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Generated</th>
                        <th class="px-4 py-3">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($reports as $report)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ $report->name }}</td>
                            <td class="px-4 py-3">{{ str($report->dashboard)->headline() }}</td>
                            <td class="px-4 py-3 uppercase">{{ $report->format }}</td>
                            <td class="px-4 py-3">{{ str($report->status)->headline() }}</td>
                            <td class="px-4 py-3">{{ $report->generated_at?->diffForHumans() }}</td>
                            <td class="px-4 py-3">
                                <a class="font-semibold text-blue-600 dark:text-blue-300" href="{{ route('admin.analytics.reports.download', $report) }}">Download</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">No reports have been generated yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $reports->links() }}
    </section>
</x-layouts.app>
