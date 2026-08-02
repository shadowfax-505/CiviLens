<x-layouts.app title="Agency Registry - CivicLens">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Organization Registry</p>
            <h1 class="text-3xl font-bold">Government Agencies</h1>
        </div>
        @can('create', App\Models\Agency::class)
            <a href="{{ route('admin.agencies.create') }}" class="rounded bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-950">Create agency</a>
        @endcan
        @if (auth()->user()?->hasRole(config('civiclens.roles.staff')) === true)
            <a href="{{ route('admin.change-requests.create', ['module' => 'agencies', 'operation' => 'create', 'subject_label' => 'New government agency']) }}" class="rounded border border-emerald-300 px-4 py-2 text-sm font-semibold text-emerald-700 dark:border-emerald-800 dark:text-emerald-300">Propose agency</a>
        @endif
    </div>

    <form method="GET" class="mt-6 grid gap-3 md:grid-cols-5">
        <input name="search" value="{{ request('search') }}" placeholder="Search agencies" class="rounded border px-3 py-2 text-slate-950">
        <select name="status" class="rounded border px-3 py-2 text-slate-950">
            <option value="">All statuses</option>
            @foreach (App\Models\Agency::STATUSES as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->headline() }}</option>
            @endforeach
        </select>
        <select name="agency_type_id" class="rounded border px-3 py-2 text-slate-950">
            <option value="">All agency types</option>
            @foreach ($agencyTypes as $type)
                <option value="{{ $type->id }}" @selected(request('agency_type_id') == $type->id)>{{ $type->name }}</option>
            @endforeach
        </select>
        <select name="sort" class="rounded border px-3 py-2 text-slate-950">
            <option value="name" @selected(request('sort') === 'name')>Name</option>
            <option value="status" @selected(request('sort') === 'status')>Status</option>
            <option value="created_at" @selected(request('sort') === 'created_at')>Created</option>
        </select>
        <button class="rounded bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950">Apply</button>
    </form>

    <div class="mt-8 overflow-x-auto rounded border dark:border-slate-800">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-100 dark:bg-slate-900">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Country</th>
                    <th class="px-4 py-3">Users</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($agencies as $agency)
                    <tr class="border-t dark:border-slate-800">
                        <td class="px-4 py-3">
                            @can('update', $agency)
                                <a class="font-semibold text-blue-700 dark:text-blue-300" href="{{ route('admin.agencies.edit', $agency) }}">{{ $agency->name }}</a>
                            @else
                                <span class="font-semibold">{{ $agency->name }}</span>
                                <a class="ml-2 text-xs font-semibold text-emerald-700 underline dark:text-emerald-300" href="{{ route('admin.change-requests.create', ['module' => 'agencies', 'operation' => 'update', 'subject_type' => App\Models\Agency::class, 'subject_id' => $agency->id, 'subject_label' => $agency->name, 'subject_url' => route('admin.agencies.index', ['search' => $agency->name])]) }}">Propose change</a>
                            @endcan
                        </td>
                        <td class="px-4 py-3">{{ $agency->type?->name }}</td>
                        <td class="px-4 py-3">{{ str($agency->status)->headline() }}</td>
                        <td class="px-4 py-3">{{ $agency->country?->name }}</td>
                        <td class="px-4 py-3">{{ $agency->users->count() }}</td>
                    </tr>
                @empty
                    <tr class="border-t dark:border-slate-800"><td colspan="5" class="px-4 py-6 text-slate-500">No agencies match the current filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $agencies->links() }}</div>
</x-layouts.app>
