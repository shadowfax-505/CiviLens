<x-layouts.app title="Budget Transparency - CivicLens">
    <div>
        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Civic Transparency</p>
        <h1 class="text-3xl font-bold">Budget Dashboard</h1>
        <p class="mt-2 max-w-2xl text-sm text-slate-600 dark:text-slate-300">Budget allocations and spending for public, active projects. Figures reflect published records only.</p>
    </div>

    <section class="mt-8 grid gap-4 md:grid-cols-4">
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Total Budget</p><p class="text-xl font-bold">{{ number_format($summary['total_budget'], 2) }}</p></div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Spent Budget</p><p class="text-xl font-bold">{{ number_format($summary['spent_budget'], 2) }}</p></div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Remaining Budget</p><p class="text-xl font-bold">{{ number_format($summary['remaining_budget'], 2) }}</p></div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Utilization</p><p class="text-xl font-bold">{{ $summary['utilization_percentage'] }}%</p></div>
    </section>

    @if ($fiscalYearSummaries->isNotEmpty())
        <section class="mt-8 space-y-6">
            <h2 class="text-xl font-bold">Budget Summary by Fiscal Year</h2>
            @foreach ($fiscalYearSummaries as $fySummary)
                <div class="rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h3 class="text-lg font-semibold">{{ $fySummary['fiscal_year']?->name }}</h3>
                        <p class="text-sm text-slate-500">
                            Combined: {{ number_format($fySummary['total_allocation'], 2) }} allocated -
                            {{ number_format($fySummary['total_spent'], 2) }} spent -
                            {{ number_format($fySummary['total_remaining'], 2) }} remaining
                        </p>
                    </div>
                    <div class="mt-4 grid gap-6 lg:grid-cols-2">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">By Agency</p>
                            <table class="mt-2 w-full text-left text-sm">
                                <tbody>
                                    @foreach ($fySummary['agencies'] as $row)
                                        <tr class="border-t dark:border-slate-800">
                                            <td class="py-2 pr-2">{{ $row['agency']?->name ?? 'Unassigned' }}</td>
                                            <td class="py-2 text-right">{{ number_format($row['total_allocation'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">By Project</p>
                            <table class="mt-2 w-full text-left text-sm">
                                <tbody>
                                    @foreach ($fySummary['projects'] as $row)
                                        <tr class="border-t dark:border-slate-800">
                                            <td class="py-2 pr-2">
                                                @if ($row['project'])
                                                    <a class="font-semibold text-blue-700 dark:text-blue-300" href="{{ route('public.projects.show', $row['project']) }}">{{ $row['project']->name }}</a>
                                                @else
                                                    <span class="text-slate-500">Unassigned</span>
                                                @endif
                                            </td>
                                            <td class="py-2 text-right">{{ number_format($row['total_allocation'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        </section>
    @endif

    <form method="GET" class="mt-8 rounded-xl border bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="grid gap-3 md:grid-cols-4">
            <input name="search" value="{{ request('search') }}" class="rounded border px-3 py-2 text-slate-950" placeholder="Search budgets">
            <select name="project_id" class="rounded border px-3 py-2 text-slate-950"><option value="">All projects</option>@foreach ($projects as $project)<option value="{{ $project->id }}" @selected(request('project_id') == $project->id)>{{ $project->name }}</option>@endforeach</select>
            <select name="agency_id" class="rounded border px-3 py-2 text-slate-950"><option value="">All agencies</option>@foreach ($agencies as $agency)<option value="{{ $agency->id }}" @selected(request('agency_id') == $agency->id)>{{ $agency->name }}</option>@endforeach</select>
            <select name="fiscal_year_id" class="rounded border px-3 py-2 text-slate-950"><option value="">All years</option>@foreach ($fiscalYears as $year)<option value="{{ $year->id }}" @selected(request('fiscal_year_id') == $year->id)>{{ $year->name }}</option>@endforeach</select>
            <button class="rounded bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950">Apply filters</button>
        </div>
    </form>

    <div class="mt-8 overflow-x-auto rounded border bg-white dark:border-slate-800 dark:bg-slate-900">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-100 dark:bg-slate-800"><tr><th class="px-4 py-3">Project</th><th class="px-4 py-3">Fiscal Year</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Allocation</th><th class="px-4 py-3">Spent</th><th class="px-4 py-3">Remaining</th></tr></thead>
            <tbody>
                @forelse ($budgets as $budget)
                    <tr class="border-t dark:border-slate-800">
                        <td class="px-4 py-3">
                            @if ($budget->project)
                                <a class="font-semibold text-blue-700 dark:text-blue-300" href="{{ route('public.projects.show', $budget->project) }}">{{ $budget->project->name }}</a>
                            @else
                                <span class="text-slate-500">Unassigned</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $budget->fiscalYear?->name }}</td>
                        <td class="px-4 py-3">{{ $budget->type?->name }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $budget->current_allocation, 2) }} {{ $budget->currency }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $budget->actual_expenditure, 2) }}</td>
                        <td class="px-4 py-3">{{ number_format($budget->remaining_balance, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-slate-500">No published budgets match the current filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $budgets->links() }}</div>
</x-layouts.app>
