<x-layouts.app title="User Administration - CivicLens">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <h1 class="text-3xl font-bold">User Administration</h1>
        <a href="{{ route('admin.users.create') }}" class="rounded bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-950">Add Administrator</a>
    </div>

    <form method="GET" action="{{ route('admin.users.index') }}" class="mt-6 grid gap-3 md:grid-cols-4">
        <input name="search" value="{{ request('search') }}" placeholder="Search users" class="rounded border px-3 py-2 text-slate-950">
        <select name="status" class="rounded border px-3 py-2 text-slate-950">
            <option value="">All statuses</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            <option value="locked" @selected(request('status') === 'locked')>Locked</option>
        </select>
        <select name="sort" class="rounded border px-3 py-2 text-slate-950">
            <option value="created_at" @selected(request('sort') === 'created_at')>Created</option>
            <option value="name" @selected(request('sort') === 'name')>Name</option>
            <option value="email" @selected(request('sort') === 'email')>Email</option>
        </select>
        <button class="rounded bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950">Apply</button>
    </form>

    <div class="mt-8 overflow-x-auto rounded border dark:border-slate-800">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-100 dark:bg-slate-900">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Roles</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr class="border-t dark:border-slate-800">
                        <td class="px-4 py-3"><a href="{{ route('admin.users.show', $user) }}" class="font-medium text-blue-600 hover:underline dark:text-blue-400">{{ $user->name }}</a></td>
                        <td class="px-4 py-3">{{ $user->email }}</td>
                        <td class="px-4 py-3">{{ $user->locked_at ? 'Locked' : ($user->is_active ? 'Active' : 'Inactive') }}</td>
                        <td class="px-4 py-3">{{ $user->roles->pluck('name')->join(', ') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $users->links() }}</div>
</x-layouts.app>
