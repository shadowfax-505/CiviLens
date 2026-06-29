<x-layouts.app :title="$title.' - CivicLens'">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Geographic Foundation</p>
            <h1 class="text-3xl font-bold">{{ $title }}</h1>
        </div>
        <a href="{{ $createRoute }}" class="rounded bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-950">Create {{ str($resourceName)->singular()->headline() }}</a>
    </div>

    <form method="GET" class="mt-6 grid gap-3 md:grid-cols-4">
        <input name="search" value="{{ request('search') }}" placeholder="Search {{ $resourceName }}" class="rounded border px-3 py-2 text-slate-950">
        <select name="sort" class="rounded border px-3 py-2 text-slate-950">
            <option value="name" @selected(request('sort') === 'name')>Name</option>
            <option value="code" @selected(request('sort') === 'code')>Code</option>
            <option value="created_at" @selected(request('sort') === 'created_at')>Created</option>
        </select>
        <select name="direction" class="rounded border px-3 py-2 text-slate-950">
            <option value="asc" @selected(request('direction') !== 'desc')>Ascending</option>
            <option value="desc" @selected(request('direction') === 'desc')>Descending</option>
        </select>
        <button class="rounded bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950">Apply filters</button>
    </form>

    <div class="mt-8 overflow-x-auto rounded border dark:border-slate-800">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-100 dark:bg-slate-900">
                <tr>
                    @foreach ($columns as $label)
                        <th class="px-4 py-3">{{ $label }}</th>
                    @endforeach
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr class="border-t dark:border-slate-800">
                        @foreach (array_keys($columns) as $column)
                            <td class="px-4 py-3">{{ data_get($record, $column) }}</td>
                        @endforeach
                        <td class="px-4 py-3">
                            <a class="text-sm font-semibold text-blue-700 dark:text-blue-300" href="{{ route('admin.geography.'.$resourceName.'.edit', $record) }}">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr class="border-t dark:border-slate-800">
                        <td class="px-4 py-6 text-slate-500" colspan="{{ count($columns) + 1 }}">No records match the current filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $records->links() }}</div>
</x-layouts.app>

