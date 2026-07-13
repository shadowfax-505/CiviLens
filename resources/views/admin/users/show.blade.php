<x-layouts.app :title="$user->name.' - User Details - CivicLens'">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div class="flex items-start gap-4">
            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-200 text-xl font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
            </div>
            <div>
                <h1 class="text-3xl font-bold">{{ $user->name }}</h1>
                <p class="text-slate-500 dark:text-slate-400">{{ $user->email }}</p>
                <p class="mt-1 text-xs text-slate-400">
                    Member since {{ $user->created_at->format('M j, Y') }}
                </p>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('admin.users.lock', $user) }}">
                @csrf
                @method('PATCH')
                @if ($user->locked_at)
                    <input type="hidden" name="locked" value="0">
                    <button class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Unlock</button>
                @else
                    <input type="hidden" name="locked" value="1">
                    <button class="rounded bg-amber-700 px-4 py-2 text-sm font-semibold text-white" onclick="return confirm('Lock this user?')">Lock</button>
                @endif
            </form>
            <button class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700" onclick="document.getElementById('password-form').classList.toggle('hidden')">Reset Password</button>
            <button class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700" onclick="document.getElementById('roles-form').classList.toggle('hidden')">Edit Roles</button>
            <a href="{{ route('admin.users.index') }}" class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700">Back</a>
        </div>
    </div>

    <form id="password-form" method="POST" action="{{ route('admin.users.password', $user) }}" class="mt-4 hidden space-y-3 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        @csrf
        @method('PUT')
        <label class="block text-sm font-medium">New Password
            <input name="password" type="text" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950" placeholder="Enter temporary password" required>
        </label>
        <button class="rounded bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-950">Set Password</button>
    </form>

    <form id="roles-form" method="POST" action="{{ route('admin.users.roles', $user) }}" class="mt-4 hidden space-y-3 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        @csrf
        @method('PUT')
        <label class="block text-sm font-medium">Roles
            <div class="mt-2 space-y-2">
                @foreach ($roles as $role)
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked($user->roles->contains($role->id)) class="rounded border-slate-300 dark:border-slate-700">
                        <span>{{ $role->name }}</span>
                    </label>
                @endforeach
            </div>
        </label>
        <button class="rounded bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-950">Update Roles</button>
    </form>

    <section class="mt-8 grid gap-4 md:grid-cols-3">
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm text-slate-500">Status</p>
            <p class="mt-1 text-lg font-semibold">
                @if ($user->locked_at)
                    <span class="text-amber-600">Locked</span>
                @elseif ($user->is_active)
                    <span class="text-emerald-600">Active</span>
                @else
                    <span class="text-red-600">Inactive</span>
                @endif
            </p>
        </div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm text-slate-500">Roles</p>
            <p class="mt-1 text-lg font-semibold">
                @if ($user->roles->isNotEmpty())
                    {{ $user->roles->pluck('name')->join(', ') }}
                @else
                    <span class="text-slate-400">None</span>
                @endif
            </p>
        </div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm text-slate-500">Last Updated</p>
            <p class="mt-1 text-lg font-semibold">{{ $user->updated_at->format('M j, Y g:i A') }}</p>
        </div>
    </section>

    <section class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-xl font-semibold">Account Details</h2>
            <dl class="mt-4 grid gap-3 text-sm">
                <div><dt class="text-slate-500">Name</dt><dd>{{ $user->name }}</dd></div>
                <div><dt class="text-slate-500">Email</dt><dd>{{ $user->email }}</dd></div>
                <div><dt class="text-slate-500">Email Verified</dt><dd>{{ $user->email_verified_at ? $user->email_verified_at->format('M j, Y g:i A') : 'Not verified' }}</dd></div>
                <div><dt class="text-slate-500">Created</dt><dd>{{ $user->created_at->format('M j, Y g:i A') }}</dd></div>
                <div><dt class="text-slate-500">Last Updated</dt><dd>{{ $user->updated_at->format('M j, Y g:i A') }}</dd></div>
                @if ($user->locked_at)
                    <div><dt class="text-slate-500">Locked At</dt><dd>{{ $user->locked_at->format('M j, Y g:i A') }}</dd></div>
                @endif
            </dl>
        </div>
        <div class="rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-xl font-semibold">Permissions</h2>
            @php
                $permissions = $user->roles->flatMap->permissions->pluck('name')->unique()->sort();
            @endphp
            @if ($permissions->isNotEmpty())
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($permissions as $permission)
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ $permission }}</span>
                    @endforeach
                </div>
            @else
                <p class="mt-4 text-sm text-slate-500">No permissions assigned.</p>
            @endif
        </div>
    </section>
</x-layouts.app>
