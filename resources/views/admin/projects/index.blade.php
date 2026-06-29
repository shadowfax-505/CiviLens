<x-layouts.app :title="($archived ? 'Archived Projects' : 'Project Dashboard').' - CivicLens'">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Project Lifecycle</p>
            <h1 class="text-3xl font-bold">{{ $archived ? 'Archived Projects' : 'Project Dashboard' }}</h1>
            <p class="mt-2 max-w-2xl text-sm text-slate-600 dark:text-slate-300">Search, filter, and manage public development projects across agencies, fiscal years, budgets, progress, and geography.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.projects.index') }}" class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700">Active</a>
            <a href="{{ route('admin.projects.archived') }}" class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700">Archived</a>
            @unless ($archived)
                <a href="{{ route('admin.projects.create') }}" class="rounded bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-950">Create project</a>
            @endunless
        </div>
    </div>

    <form method="GET" class="mt-8 rounded-xl border bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="grid gap-3 md:grid-cols-4">
            <label class="text-sm">Global search
                <input name="search" value="{{ request('search') }}" class="mt-1 w-full rounded border px-3 py-2 text-slate-950" placeholder="Code, name, description">
            </label>
            <label class="text-sm">Project code
                <input name="project_code" value="{{ request('project_code') }}" class="mt-1 w-full rounded border px-3 py-2 text-slate-950" placeholder="PRJ">
            </label>
            <label class="text-sm">Agency
                <select name="agency_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                    <option value="">All agencies</option>
                    @foreach ($agencies as $agency)
                        <option value="{{ $agency->id }}" @selected(request('agency_id') == $agency->id)>{{ $agency->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Status
                <select name="project_status_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->id }}" @selected(request('project_status_id') == $status->id)>{{ $status->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Category
                <select name="project_category_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(request('project_category_id') == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Priority
                <select name="project_priority_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                    <option value="">All priorities</option>
                    @foreach ($priorities as $priority)
                        <option value="{{ $priority->id }}" @selected(request('project_priority_id') == $priority->id)>{{ $priority->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Funding
                <select name="funding_source_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                    <option value="">All funding</option>
                    @foreach ($fundingSources as $source)
                        <option value="{{ $source->id }}" @selected(request('funding_source_id') == $source->id)>{{ $source->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Fiscal year
                <select name="fiscal_year_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                    <option value="">All years</option>
                    @foreach ($fiscalYears as $year)
                        <option value="{{ $year->id }}" @selected(request('fiscal_year_id') == $year->id)>{{ $year->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Country
                <select name="country_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                    <option value="">All countries</option>
                    @foreach ($countries as $country)
                        <option value="{{ $country->id }}" @selected(request('country_id') == $country->id)>{{ $country->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Budget min
                <input name="budget_min" value="{{ request('budget_min') }}" class="mt-1 w-full rounded border px-3 py-2 text-slate-950" type="number" min="0">
            </label>
            <label class="text-sm">Budget max
                <input name="budget_max" value="{{ request('budget_max') }}" class="mt-1 w-full rounded border px-3 py-2 text-slate-950" type="number" min="0">
            </label>
            <label class="text-sm">Progress range
                <div class="mt-1 flex gap-2">
                    <input name="progress_min" value="{{ request('progress_min') }}" class="w-full rounded border px-3 py-2 text-slate-950" type="number" min="0" max="100" placeholder="Min">
                    <input name="progress_max" value="{{ request('progress_max') }}" class="w-full rounded border px-3 py-2 text-slate-950" type="number" min="0" max="100" placeholder="Max">
                </div>
            </label>
            <label class="text-sm">Planned start from
                <input name="planned_start_from" value="{{ request('planned_start_from') }}" class="mt-1 w-full rounded border px-3 py-2 text-slate-950" type="date">
            </label>
            <label class="text-sm">Planned start to
                <input name="planned_start_to" value="{{ request('planned_start_to') }}" class="mt-1 w-full rounded border px-3 py-2 text-slate-950" type="date">
            </label>
            <label class="text-sm">Sort
                <select name="sort" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                    <option value="created_at" @selected(request('sort') === 'created_at')>Created</option>
                    <option value="project_code" @selected(request('sort') === 'project_code')>Project code</option>
                    <option value="name" @selected(request('sort') === 'name')>Name</option>
                    <option value="approved_budget" @selected(request('sort') === 'approved_budget')>Approved budget</option>
                    <option value="progress_percentage" @selected(request('sort') === 'progress_percentage')>Progress</option>
                    <option value="planned_start_date" @selected(request('sort') === 'planned_start_date')>Planned start</option>
                </select>
            </label>
            <label class="text-sm">Direction
                <select name="direction" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                    <option value="desc" @selected(request('direction') !== 'asc')>Descending</option>
                    <option value="asc" @selected(request('direction') === 'asc')>Ascending</option>
                </select>
            </label>
        </div>
        <div class="mt-4 flex gap-3">
            <button class="rounded bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-950">Apply filters</button>
            <a href="{{ $archived ? route('admin.projects.archived') : route('admin.projects.index') }}" class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700">Reset</a>
        </div>
    </form>

    <div class="mt-8 overflow-x-auto rounded border bg-white dark:border-slate-800 dark:bg-slate-900">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-100 dark:bg-slate-800">
                <tr>
                    <th class="px-4 py-3">Project</th>
                    <th class="px-4 py-3">Agency</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Budget</th>
                    <th class="px-4 py-3">Progress</th>
                    <th class="px-4 py-3">Dates</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($projects as $project)
                    <tr class="border-t dark:border-slate-800">
                        <td class="px-4 py-3">
                            <a class="font-semibold text-blue-700 dark:text-blue-300" href="{{ route('admin.projects.show', $project) }}">{{ $project->name }}</a>
                            <div class="text-xs text-slate-500">{{ $project->project_code }}</div>
                        </td>
                        <td class="px-4 py-3">{{ $project->agency?->name }}</td>
                        <td class="px-4 py-3">{{ $project->status?->name }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $project->approved_budget, 2) }}</td>
                        <td class="px-4 py-3">{{ $project->progress_percentage }}%</td>
                        <td class="px-4 py-3">{{ $project->planned_start_date?->format('Y-m-d') }} -> {{ $project->planned_end_date?->format('Y-m-d') }}</td>
                    </tr>
                @empty
                    <tr class="border-t dark:border-slate-800">
                        <td class="px-4 py-8 text-slate-500" colspan="6">No projects match the current filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $projects->links() }}</div>
</x-layouts.app>

