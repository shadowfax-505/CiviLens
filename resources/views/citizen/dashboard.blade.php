<x-layouts.app title="My CivicLens Dashboard">
    <section class="space-y-8">
        <div class="cl-page-hero">
            <p class="cl-kicker text-cyan-100">Citizen dashboard</p>
            <h1 class="cl-page-title mt-3">My reports and public information</h1>
            <p class="cl-page-copy">Track reports you submitted and explore approved public civic records.</p>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            @foreach (['Projects' => $publicCounts['projects'], 'Public tenders' => $publicCounts['tenders'], 'Public documents' => $publicCounts['documents']] as $label => $value)
                <article class="cl-card">
                    <p class="cl-kicker">{{ $label }}</p>
                    <p class="mt-3 text-3xl font-bold text-slate-950 dark:text-white">{{ number_format($value) }}</p>
                </article>
            @endforeach
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="cl-card">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="cl-card-title">My report statuses</h2>
                    <a href="{{ route('citizen.reports.index') }}" class="cl-link">View all reports</a>
                </div>
                <div class="mt-4 space-y-2">
                    @forelse ($reportStatusCounts as $status => $total)
                        <p class="flex justify-between rounded-xl bg-slate-50 px-4 py-3 text-sm dark:bg-slate-800/60"><span>{{ $status }}</span><strong>{{ number_format($total) }}</strong></p>
                    @empty
                        <p class="cl-muted">You have not submitted a report yet.</p>
                    @endforelse
                </div>
            </section>

            <section class="cl-card">
                <h2 class="cl-card-title">Explore public information</h2>
                <div class="mt-4 flex flex-wrap gap-3">
                    <a class="cl-button" href="{{ route('public.projects.index') }}">Project map</a>
                    <a class="cl-button" href="{{ route('public.search') }}">Public search</a>
                    <a class="cl-button" href="{{ route('public.reports.create') }}">Submit a report</a>
                </div>
            </section>
        </div>

        <section class="cl-card">
            <h2 class="cl-card-title">Recent submitted reports</h2>
            <div class="mt-4 space-y-3">
                @forelse ($reports as $report)
                    <a href="{{ route('citizen.reports.show', $report) }}" class="block rounded-xl bg-slate-50 px-4 py-3 dark:bg-slate-800/60">
                        <p class="font-semibold text-slate-950 dark:text-white">{{ $report->title }}</p>
                        <p class="mt-1 text-sm cl-muted">{{ $report->status?->name ?? 'Queued for review' }} · {{ $report->submitted_at?->format('M j, Y') }}</p>
                    </a>
                @empty
                    <p class="cl-muted">No reports have been submitted.</p>
                @endforelse
            </div>
        </section>
    </section>
</x-layouts.app>
