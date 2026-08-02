<x-layouts.app title="CivicLens Analytics">
    @php
        $activeAnalyticsFilters = collect($filters->toArray())->except(['dashboard', 'period', 'format', 'metric'])->reject(fn ($value) => blank($value));
    @endphp

    <section class="space-y-8">
        <div class="rounded-3xl bg-slate-950 p-8 text-white shadow-sm dark:bg-slate-900">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.24em] text-blue-300">Business Intelligence</p>
                    <h1 class="mt-3 text-4xl font-bold tracking-tight">Executive Decision Dashboard</h1>
                    <p class="mt-3 max-w-3xl text-slate-300">Reusable analytics generated from normalized CivicLens source records, with snapshots, reports, alerts, and chart-ready definitions.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('admin.analytics.snapshots.realtime') }}">
                        @csrf
                        <input type="hidden" name="dashboard" value="{{ $payload['dashboard'] }}">
                        <input type="hidden" name="period" value="daily">
                        @foreach ($activeAnalyticsFilters as $name => $value)
                            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                        @endforeach
                        <button type="submit" class="rounded-full bg-white px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-blue-50">Generate Snapshot Now</button>
                    </form>
                    <form method="POST" action="{{ route('admin.analytics.reports.csv') }}">
                        @csrf
                        <input type="hidden" name="dashboard" value="{{ $payload['dashboard'] }}">
                        @foreach ($activeAnalyticsFilters as $name => $value)
                            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                        @endforeach
                        <button type="submit" class="rounded-full bg-white px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-blue-50">Download CSV Now</button>
                    </form>
                </div>
            </div>
        </div>

        <nav class="flex flex-wrap gap-2" aria-label="Analytics dashboards">
            @foreach ($dashboards as $key => $label)
                <a href="{{ route('admin.analytics.index', array_merge(request()->except('page'), ['dashboard' => $key])) }}" class="rounded-full border px-4 py-2 text-sm font-semibold {{ $payload['dashboard'] === $key ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-blue-300 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <form method="GET" action="{{ route('admin.analytics.index') }}" class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 md:grid-cols-2 xl:grid-cols-4">
            <input type="hidden" name="dashboard" value="{{ $payload['dashboard'] }}">
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                Date from
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="mt-1 w-full rounded-lg border-slate-300 bg-white text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
            </label>
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                Date to
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="mt-1 w-full rounded-lg border-slate-300 bg-white text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
            </label>
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                Fiscal year
                <select name="fiscal_year_id" class="mt-1 w-full rounded-lg border-slate-300 bg-white text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">All fiscal years</option>
                    @foreach ($fiscalYears as $fiscalYear)
                        <option value="{{ $fiscalYear->id }}" @selected((string) request('fiscal_year_id') === (string) $fiscalYear->id)>{{ $fiscalYear->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                Agency
                <select name="agency_id" class="mt-1 w-full rounded-lg border-slate-300 bg-white text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">All agencies</option>
                    @foreach ($agencies as $agency)
                        <option value="{{ $agency->id }}" @selected((string) request('agency_id') === (string) $agency->id)>{{ $agency->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                Division
                <select name="division_id" class="mt-1 w-full rounded-lg border-slate-300 bg-white text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">All divisions</option>
                    @foreach ($divisions as $division)
                        <option value="{{ $division->id }}" @selected((string) request('division_id') === (string) $division->id)>{{ $division->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                District
                <select name="district_id" class="mt-1 w-full rounded-lg border-slate-300 bg-white text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">All districts</option>
                    @foreach ($districts as $district)
                        <option value="{{ $district->id }}" @selected((string) request('district_id') === (string) $district->id)>{{ $district->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                Funding source
                <select name="funding_source_id" class="mt-1 w-full rounded-lg border-slate-300 bg-white text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">All funding sources</option>
                    @foreach ($fundingSources as $fundingSource)
                        <option value="{{ $fundingSource->id }}" @selected((string) request('funding_source_id') === (string) $fundingSource->id)>{{ $fundingSource->name }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex items-end">
                <button type="submit" class="w-full rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200">Apply Filters</button>
            </div>
        </form>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($payload['metrics'] as $metric)
                @include('admin.analytics.partials.kpi-card', ['metric' => $metric])
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            @foreach ($payload['charts'] as $chart)
                @include('admin.analytics.partials.chart-panel', ['chart' => $chart])
            @endforeach
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:col-span-2">
                <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Forecast Preparation</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">This is rule and feature preparation only; no AI model output is generated in v1.</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($payload['forecast']['features'] as $feature)
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ str($feature)->headline() }}</span>
                    @endforeach
                </div>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Alerts and Recommendations</h2>
                    <a href="{{ route('admin.analytics.alerts') }}" class="text-sm font-semibold text-blue-600 dark:text-blue-300">View all</a>
                </div>
                <div class="mt-4 space-y-3">
                    @forelse ($recentAlerts as $alert)
                        <article class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $alert->title }}</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $alert->message }}</p>
                        </article>
                    @empty
                        <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">No active analytics alerts for the selected dashboard.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </section>
</x-layouts.app>
