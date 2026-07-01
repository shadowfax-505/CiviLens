<x-layouts.app title="Intelligence Processing Queue">
    <section class="space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-amber-600">Processing Readiness</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-950 dark:text-white">Intelligence Processing Jobs</h1>
            <p class="mt-2 max-w-2xl text-sm text-slate-500">Preparation jobs for future OCR, AI review, and search synchronization. They do not perform real OCR, LLM calls, or embedding generation.</p>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60">
                    <tr>
                        <th class="px-5 py-3">Job Type</th>
                        <th class="px-5 py-3">Target</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Queued</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($jobs as $job)
                        <tr>
                            <td class="px-5 py-4 font-semibold text-slate-950 dark:text-white">{{ str($job->job_type)->headline() }}</td>
                            <td class="px-5 py-4">{{ class_basename($job->target_type) }} #{{ $job->target_id }}</td>
                            <td class="px-5 py-4">{{ str($job->status)->headline() }}</td>
                            <td class="px-5 py-4">{{ $job->queued_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-8 text-center text-slate-500">No processing jobs are queued.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                {{ $jobs->links() }}
            </div>
        </div>
    </section>
</x-layouts.app>
