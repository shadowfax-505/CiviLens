<x-layouts.app :title="($archived ? 'Archived Tenders' : 'Procurement Dashboard').' - CivicLens'">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Procurement Core</p>
            <h1 class="text-3xl font-bold">{{ $archived ? 'Archived Tenders' : 'Procurement Dashboard' }}</h1>
            <p class="mt-2 max-w-2xl text-sm text-slate-600 dark:text-slate-300">Tender, evaluation, award, and contract records remain traceable through an immutable procurement timeline.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.procurement.plans.index') }}" class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700">Plans</a>
            <a href="{{ route('admin.procurement.tenders.index') }}" class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700">Active</a>
            <a href="{{ route('admin.procurement.tenders.archived') }}" class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700">Archived</a>
            @unless ($archived)
                <a href="{{ route('admin.procurement.tenders.create') }}" class="rounded bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-950">Create tender</a>
            @endunless
        </div>
    </div>

    <section class="mt-8 grid gap-4 md:grid-cols-4">
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Active Tenders</p><p class="text-xl font-bold">{{ $summary['active_tenders'] }}</p></div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Closed Tenders</p><p class="text-xl font-bold">{{ $summary['closed_tenders'] }}</p></div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Average Bidders</p><p class="text-xl font-bold">{{ $summary['average_bidders'] }}</p></div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Award Rate</p><p class="text-xl font-bold">{{ $summary['award_rate'] }}%</p></div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Avg Evaluation Time</p><p class="text-xl font-bold">{{ $summary['average_evaluation_days'] }} days</p></div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Single-Bid Tenders</p><p class="text-xl font-bold">{{ $summary['enterprise_metrics']['single_bid_tenders'] }}</p></div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Variation Frequency</p><p class="text-xl font-bold">{{ $summary['enterprise_metrics']['variation_frequency'] }}</p></div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900 md:col-span-2"><p class="text-sm text-slate-500">Contract Status Summary</p><p class="text-xl font-bold">{{ collect($summary['contract_status_summary'])->map(fn ($total, $status) => "{$status}: {$total}")->implode(' | ') ?: 'No contracts yet' }}</p></div>
    </section>

    <form method="GET" class="mt-8 rounded-xl border bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="grid gap-3 md:grid-cols-4">
            <input name="search" value="{{ request('search') }}" class="rounded border px-3 py-2 text-slate-950" placeholder="Global search">
            <select name="project_id" class="rounded border px-3 py-2 text-slate-950"><option value="">All projects</option>@foreach ($projects as $project)<option value="{{ $project->id }}" @selected(request('project_id') == $project->id)>{{ $project->name }}</option>@endforeach</select>
            <select name="agency_id" class="rounded border px-3 py-2 text-slate-950"><option value="">All agencies</option>@foreach ($agencies as $agency)<option value="{{ $agency->id }}" @selected(request('agency_id') == $agency->id)>{{ $agency->name }}</option>@endforeach</select>
            <select name="procurement_method_id" class="rounded border px-3 py-2 text-slate-950"><option value="">All methods</option>@foreach ($methods as $method)<option value="{{ $method->id }}" @selected(request('procurement_method_id') == $method->id)>{{ $method->name }}</option>@endforeach</select>
            <select name="tender_category_id" class="rounded border px-3 py-2 text-slate-950"><option value="">All categories</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected(request('tender_category_id') == $category->id)>{{ $category->name }}</option>@endforeach</select>
            <select name="tender_status_id" class="rounded border px-3 py-2 text-slate-950"><option value="">All statuses</option>@foreach ($statuses as $status)<option value="{{ $status->id }}" @selected(request('tender_status_id') == $status->id)>{{ $status->name }}</option>@endforeach</select>
            <select name="fiscal_year_id" class="rounded border px-3 py-2 text-slate-950"><option value="">All fiscal years</option>@foreach ($fiscalYears as $year)<option value="{{ $year->id }}" @selected(request('fiscal_year_id') == $year->id)>{{ $year->name }}</option>@endforeach</select>
            <select name="sort" class="rounded border px-3 py-2 text-slate-950"><option value="created_at">Created</option><option value="tender_number" @selected(request('sort') === 'tender_number')>Tender Number</option><option value="closing_at" @selected(request('sort') === 'closing_at')>Closing Date</option></select>
            <input name="budget_min" value="{{ request('budget_min') }}" class="rounded border px-3 py-2 text-slate-950" type="number" min="0" placeholder="Budget min">
            <input name="budget_max" value="{{ request('budget_max') }}" class="rounded border px-3 py-2 text-slate-950" type="number" min="0" placeholder="Budget max">
            <input name="winning_bidder" value="{{ request('winning_bidder') }}" class="rounded border px-3 py-2 text-slate-950" placeholder="Winning bidder">
            <button class="rounded bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950">Apply filters</button>
        </div>
    </form>

    <div class="mt-8 overflow-x-auto rounded border bg-white dark:border-slate-800 dark:bg-slate-900">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-100 dark:bg-slate-800"><tr><th class="px-4 py-3">Tender</th><th class="px-4 py-3">Project</th><th class="px-4 py-3">Agency</th><th class="px-4 py-3">Method</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Budget</th></tr></thead>
            <tbody>
                @forelse ($tenders as $tender)
                    <tr class="border-t dark:border-slate-800">
                        <td class="px-4 py-3"><a class="font-semibold text-blue-700 dark:text-blue-300" href="{{ route('admin.procurement.tenders.show', $tender) }}">{{ $tender->tender_number }}</a><div class="text-xs text-slate-500">{{ $tender->title }}</div></td>
                        <td class="px-4 py-3">{{ $tender->project?->name }}</td>
                        <td class="px-4 py-3">{{ $tender->agency?->name }}</td>
                        <td class="px-4 py-3">{{ $tender->method?->name }}</td>
                        <td class="px-4 py-3">{{ $tender->status?->name }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $tender->budget?->current_allocation, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-slate-500">No tenders match the current filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $tenders->links() }}</div>
</x-layouts.app>
