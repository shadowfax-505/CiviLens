<x-layouts.app :title="($archived ? 'Archived Budgets' : 'Financial Dashboard').' - CivicLens'">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Financial Core</p>
            <h1 class="text-3xl font-bold">{{ $archived ? 'Archived Budgets' : 'Budget Dashboard' }}</h1>
            <p class="mt-2 max-w-2xl text-sm text-slate-600 dark:text-slate-300">Budget records are the financial source of truth for project allocations, revisions, transactions, and spending.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.finance.budgets.index') }}" class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700">Active</a>
            <a href="{{ route('admin.finance.budgets.archived') }}" class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700">Archived</a>
            @unless ($archived)
                <a href="{{ route('admin.finance.budgets.create') }}" class="rounded bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-950">Create budget</a>
            @endunless
        </div>
    </div>

    <section class="mt-8 grid gap-4 md:grid-cols-4">
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Total Budget</p><p class="text-xl font-bold">{{ number_format($summary['total_budget'], 2) }}</p></div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Spent Budget</p><p class="text-xl font-bold">{{ number_format($summary['spent_budget'], 2) }}</p></div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Remaining Budget</p><p class="text-xl font-bold">{{ number_format($summary['remaining_budget'], 2) }}</p></div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Utilization</p><p class="text-xl font-bold">{{ $summary['utilization_percentage'] }}%</p></div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Allocated Budget</p><p class="text-xl font-bold">{{ number_format($summary['allocated_budget'], 2) }}</p></div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Revision Count</p><p class="text-xl font-bold">{{ $summary['revision_count'] }}</p></div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900 md:col-span-2"><p class="text-sm text-slate-500">Project Financial Health</p><p class="text-xl font-bold">{{ $summary['financial_health'] }}</p></div>
    </section>

    <form method="GET" class="mt-8 rounded-xl border bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="grid gap-3 md:grid-cols-4">
            <input name="search" value="{{ request('search') }}" class="rounded border px-3 py-2 text-slate-950" placeholder="Search budgets">
            <select name="project_id" class="rounded border px-3 py-2 text-slate-950"><option value="">All projects</option>@foreach ($projects as $project)<option value="{{ $project->id }}" @selected(request('project_id') == $project->id)>{{ $project->name }}</option>@endforeach</select>
            <select name="agency_id" class="rounded border px-3 py-2 text-slate-950"><option value="">All agencies</option>@foreach ($agencies as $agency)<option value="{{ $agency->id }}" @selected(request('agency_id') == $agency->id)>{{ $agency->name }}</option>@endforeach</select>
            <select name="fiscal_year_id" class="rounded border px-3 py-2 text-slate-950"><option value="">All years</option>@foreach ($fiscalYears as $year)<option value="{{ $year->id }}" @selected(request('fiscal_year_id') == $year->id)>{{ $year->name }}</option>@endforeach</select>
            <select name="funding_source_id" class="rounded border px-3 py-2 text-slate-950"><option value="">All funding</option>@foreach ($fundingSources as $source)<option value="{{ $source->id }}" @selected(request('funding_source_id') == $source->id)>{{ $source->name }}</option>@endforeach</select>
            <select name="budget_type_id" class="rounded border px-3 py-2 text-slate-950"><option value="">All types</option>@foreach ($types as $type)<option value="{{ $type->id }}" @selected(request('budget_type_id') == $type->id)>{{ $type->name }}</option>@endforeach</select>
            <select name="budget_status_id" class="rounded border px-3 py-2 text-slate-950"><option value="">All statuses</option>@foreach ($statuses as $status)<option value="{{ $status->id }}" @selected(request('budget_status_id') == $status->id)>{{ $status->name }}</option>@endforeach</select>
            <select name="sort" class="rounded border px-3 py-2 text-slate-950"><option value="created_at">Created</option><option value="current_allocation" @selected(request('sort') === 'current_allocation')>Allocation</option><option value="actual_expenditure" @selected(request('sort') === 'actual_expenditure')>Spent</option></select>
            <input name="amount_min" value="{{ request('amount_min') }}" class="rounded border px-3 py-2 text-slate-950" type="number" min="0" placeholder="Amount min">
            <input name="amount_max" value="{{ request('amount_max') }}" class="rounded border px-3 py-2 text-slate-950" type="number" min="0" placeholder="Amount max">
            <input name="created_from" value="{{ request('created_from') }}" class="rounded border px-3 py-2 text-slate-950" type="date">
            <button class="rounded bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950">Apply filters</button>
        </div>
    </form>

    <div class="mt-8 overflow-x-auto rounded border bg-white dark:border-slate-800 dark:bg-slate-900">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-100 dark:bg-slate-800"><tr><th class="px-4 py-3">Project</th><th class="px-4 py-3">Fiscal Year</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Allocation</th><th class="px-4 py-3">Spent</th><th class="px-4 py-3">Remaining</th></tr></thead>
            <tbody>
                @forelse ($budgets as $budget)
                    <tr class="border-t dark:border-slate-800">
                        <td class="px-4 py-3"><a class="font-semibold text-blue-700 dark:text-blue-300" href="{{ route('admin.finance.budgets.show', $budget) }}">{{ $budget->project?->name }}</a><div class="text-xs text-slate-500">{{ $budget->notes }}</div></td>
                        <td class="px-4 py-3">{{ $budget->fiscalYear?->name }}</td>
                        <td class="px-4 py-3">{{ $budget->type?->name }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $budget->current_allocation, 2) }} {{ $budget->currency }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $budget->actual_expenditure, 2) }}</td>
                        <td class="px-4 py-3">{{ number_format($budget->remaining_balance, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-slate-500">No budgets match the current filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $budgets->links() }}</div>
</x-layouts.app>

