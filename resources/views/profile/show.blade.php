<x-layouts.app title="Profile - CivicLens">
    <h1 class="text-3xl font-bold">Profile</h1>

    <div class="mt-8 grid gap-8 lg:grid-cols-2">
        <form method="POST" action="{{ route('profile.update') }}" class="space-y-4 rounded border p-5 dark:border-slate-800">
            @csrf
            @method('PUT')
            <h2 class="font-semibold">Account Details</h2>
            <input name="name" value="{{ old('name', $user->name) }}" class="w-full rounded border px-3 py-2 text-slate-950">
            <input name="email" type="email" value="{{ old('email', $user->email) }}" class="w-full rounded border px-3 py-2 text-slate-950">
            <button class="rounded bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950">Save profile</button>
        </form>

        <form method="POST" action="{{ route('profile.avatar') }}" enctype="multipart/form-data" class="space-y-4 rounded border p-5 dark:border-slate-800">
            @csrf
            <h2 class="font-semibold">Avatar</h2>
            <input name="avatar" type="file" accept="image/*">
            <button class="rounded bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950">Upload avatar</button>
        </form>

        <form method="POST" action="{{ route('profile.password') }}" class="space-y-4 rounded border p-5 dark:border-slate-800">
            @csrf
            @method('PUT')
            <h2 class="font-semibold">Change Password</h2>
            <input name="current_password" type="password" placeholder="Current password" class="w-full rounded border px-3 py-2 text-slate-950">
            <input name="password" type="password" placeholder="New password" class="w-full rounded border px-3 py-2 text-slate-950">
            <input name="password_confirmation" type="password" placeholder="Confirm password" class="w-full rounded border px-3 py-2 text-slate-950">
            <button class="rounded bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950">Change password</button>
        </form>

        <form method="POST" action="{{ route('profile.notifications') }}" class="space-y-4 rounded border p-5 dark:border-slate-800">
            @csrf
            @method('PUT')
            <h2 class="font-semibold">Notification Preferences</h2>
            <label class="flex gap-2"><input type="checkbox" name="email_reports" value="1" @checked(data_get($user->notification_preferences, 'email_reports'))> Email reports</label>
            <label class="flex gap-2"><input type="checkbox" name="security_alerts" value="1" @checked(data_get($user->notification_preferences, 'security_alerts'))> Security alerts</label>
            <button class="rounded bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950">Save preferences</button>
        </form>
    </div>

    <section class="mt-10">
        <h2 class="font-semibold">Account Activity</h2>
        <ul class="mt-4 divide-y divide-slate-200 rounded border dark:divide-slate-800 dark:border-slate-800">
            @forelse ($activities as $activity)
                <li class="px-4 py-3">{{ $activity->event }} <span class="text-sm text-slate-500">{{ $activity->created_at->diffForHumans() }}</span></li>
            @empty
                <li class="px-4 py-3 text-slate-500">No activity recorded yet.</li>
            @endforelse
        </ul>
    </section>
</x-layouts.app>

