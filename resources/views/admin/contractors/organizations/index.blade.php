<x-layouts.app :title="($archived ? 'Archived Contractors' : 'Contractor Intelligence').' - CivicLens'">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Contractor Intelligence</p>
            <h1 class="text-3xl font-bold">{{ $archived ? 'Archived Contractor Organizations' : 'Contractor Dashboard' }}</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-600 dark:text-slate-300">Track vendor identity, compliance, risk, licenses, performance snapshots, and public-project delivery history.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.contractors.organizations.index') }}" class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700">Active</a>
            <a href="{{ route('admin.contractors.organizations.archived') }}" class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700">Archived</a>
            @unless ($archived)
                @can('create', App\Models\Organization::class)
                    <a href="{{ route('admin.contractors.organizations.create') }}" class="rounded bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-950">Create organization</a>
                @endcan
                @if (auth()->user()?->hasRole(config('civiclens.roles.staff')) === true)
                    <a href="{{ route('admin.change-requests.create', ['module' => 'contractors', 'operation' => 'create', 'subject_label' => 'New contractor organization', 'subject_url' => route('admin.contractors.organizations.index')]) }}" class="rounded border border-emerald-300 px-4 py-2 text-sm font-semibold text-emerald-700 dark:border-emerald-800 dark:text-emerald-300">Propose organization</a>
                @endif
            @endunless
        </div>
    </div>

    <div class="mt-8 grid gap-4 md:grid-cols-4">
        @foreach ([
            'Organizations' => $summary['organizations'],
            'Contractor Profiles' => $summary['contractors'],
            'Blacklisted' => $summary['blacklisted'],
            'Failed Compliance' => $summary['failed_compliance'],
            'Suspended' => $summary['suspended'],
            'Expiring Licenses' => $summary['expiring_licenses'],
            'Average Delay' => $summary['average_delay'].' days',
            'Average Evaluation' => $summary['average_evaluation'].'%',
        ] as $label => $value)
            <div class="rounded-xl border bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</div>
                <div class="mt-2 text-2xl font-bold">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <form method="GET" class="mt-8 rounded-xl border bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="grid gap-3 md:grid-cols-4">
            <label class="text-sm">Global search
                <input name="search" value="{{ request('search') }}" class="mt-1 w-full rounded border px-3 py-2 text-slate-950" placeholder="Company, license, certification">
            </label>
            <label class="text-sm">Registration number
                <input name="registration_number" value="{{ request('registration_number') }}" class="mt-1 w-full rounded border px-3 py-2 text-slate-950" placeholder="REG">
            </label>
            <label class="text-sm">Company type
                <select name="organization_company_type_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                    <option value="">All types</option>
                    @foreach ($companyTypes as $type)
                        <option value="{{ $type->id }}" @selected(request('organization_company_type_id') == $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Industry
                <select name="organization_industry_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                    <option value="">All industries</option>
                    @foreach ($industries as $industry)
                        <option value="{{ $industry->id }}" @selected(request('organization_industry_id') == $industry->id)>{{ $industry->name }}</option>
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
            <label class="text-sm">Status
                <select name="status" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                    <option value="">All statuses</option>
                    @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended', 'archived' => 'Archived'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Risk
                <select name="risk_level_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                    <option value="">All risk levels</option>
                    @foreach ($riskLevels as $riskLevel)
                        <option value="{{ $riskLevel->id }}" @selected(request('risk_level_id') == $riskLevel->id)>{{ $riskLevel->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Sort
                <select name="sort" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                    <option value="created_at" @selected(request('sort') === 'created_at')>Created</option>
                    <option value="legal_name" @selected(request('sort') === 'legal_name')>Legal name</option>
                    <option value="registration_number" @selected(request('sort') === 'registration_number')>Registration</option>
                    <option value="status" @selected(request('sort') === 'status')>Status</option>
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
            <a href="{{ $archived ? route('admin.contractors.organizations.archived') : route('admin.contractors.organizations.index') }}" class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700">Reset</a>
        </div>
    </form>

    <div class="mt-8 overflow-x-auto rounded border bg-white dark:border-slate-800 dark:bg-slate-900">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-100 dark:bg-slate-800">
                <tr>
                    <th class="px-4 py-3">Organization</th>
                    <th class="px-4 py-3">Industry</th>
                    <th class="px-4 py-3">Risk</th>
                    <th class="px-4 py-3">Compliance</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($organizations as $organization)
                    <tr class="border-t dark:border-slate-800">
                        <td class="px-4 py-3">
                            <a class="font-semibold text-blue-700 dark:text-blue-300" href="{{ route('admin.contractors.organizations.show', $organization) }}">{{ $organization->legal_name }}</a>
                            <div class="text-xs text-slate-500">{{ $organization->registration_number }}</div>
                        </td>
                        <td class="px-4 py-3">{{ $organization->industry?->name }}</td>
                        <td class="px-4 py-3">{{ $organization->profile?->riskLevel?->name ?? 'Profile pending' }}</td>
                        <td class="px-4 py-3">{{ $organization->profile?->complianceRecords()->count() ?? 0 }} records</td>
                        <td class="px-4 py-3">{{ ucfirst($organization->status) }}</td>
                    </tr>
                @empty
                    <tr class="border-t dark:border-slate-800">
                        <td class="px-4 py-8 text-slate-500" colspan="5">No contractor organizations match the current filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $organizations->links() }}</div>
</x-layouts.app>
