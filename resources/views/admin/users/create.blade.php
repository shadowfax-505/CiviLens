<x-layouts.app title="Create User - CivicLens">
    <section class="mx-auto max-w-4xl space-y-8">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">User Administration</p>
            <h1 class="text-3xl font-bold">Create User</h1>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">The account will remain unverified until the user confirms their email address.</p>
        </div>

        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-6 rounded-xl border bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            @csrf
            <div class="grid gap-4 md:grid-cols-2">
                <label class="block">
                    <span class="text-sm font-medium">Name</span>
                    <input name="name" value="{{ old('name') }}" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950" required>
                </label>
                <label class="block">
                    <span class="text-sm font-medium">Email</span>
                    <input name="email" type="email" value="{{ old('email') }}" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950" required>
                </label>
                <label class="block">
                    <span class="text-sm font-medium">Temporary Password</span>
                    <input name="password" type="password" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950" required>
                </label>
                <label class="block">
                    <span class="text-sm font-medium">Confirm Password</span>
                    <input name="password_confirmation" type="password" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950" required>
                </label>
            </div>

            <label class="flex items-center gap-2 text-sm font-medium">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="rounded border-slate-300 dark:border-slate-700">
                Active
            </label>

            <div>
                <p class="text-sm font-medium">Roles</p>
                <div class="mt-3 grid gap-3 md:grid-cols-2">
                    @foreach ($roles as $role)
                        <label class="flex items-center gap-2 rounded-lg border border-slate-200 p-3 dark:border-slate-800">
                            <input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(in_array($role->id, old('roles', []))) class="rounded border-slate-300 dark:border-slate-700">
                            <span>{{ $role->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex gap-3">
                <button class="rounded bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-950">Create User</button>
                <a href="{{ route('admin.users.index') }}" class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700">Cancel</a>
            </div>
        </form>
    </section>
</x-layouts.app>
