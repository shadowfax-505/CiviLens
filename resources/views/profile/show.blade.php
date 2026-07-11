@php
    $preferences = $user->notification_preferences ?? [];
    $appearance = old('appearance', data_get($preferences, 'appearance', 'system'));
@endphp

<x-layouts.app title="Settings - CivicLens">
    <section class="space-y-8">
        <div class="cl-page-hero">
            <p class="cl-kicker text-cyan-100">Operator Settings</p>
            <h1 class="cl-page-title mt-3">Settings</h1>
            <p class="cl-page-copy">Manage identity, security, notifications, appearance, and account activity without changing CivicLens deployment behavior.</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <form method="POST" action="{{ route('profile.update') }}" class="cl-form-card space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <p class="cl-kicker">Identity</p>
                    <h2 class="cl-card-title mt-1">Account Details</h2>
                </div>
                <label class="grid gap-2 text-sm font-semibold">
                    Name
                    <input name="name" value="{{ old('name', $user->name) }}">
                </label>
                <label class="grid gap-2 text-sm font-semibold">
                    Email
                    <input name="email" type="email" value="{{ old('email', $user->email) }}">
                </label>
                <button class="cl-button-primary" type="submit">Save settings</button>
            </form>

            <form method="POST" action="{{ route('profile.avatar') }}" enctype="multipart/form-data" class="cl-form-card space-y-4">
                @csrf
                <div>
                    <p class="cl-kicker">Visual Identity</p>
                    <h2 class="cl-card-title mt-1">Avatar</h2>
                </div>
                <x-upload-zone
                    name="avatar"
                    accept="image/png,image/jpeg,image/gif,image/webp"
                    label="Choose avatar image"
                    hint="PNG, JPG, GIF, or WebP up to 2 MB. Click or drag and drop."
                    required
                />
                <button class="cl-button-primary" type="submit">Upload avatar</button>
            </form>

            <form method="POST" action="{{ route('profile.password') }}" class="cl-form-card space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <p class="cl-kicker">Security</p>
                    <h2 class="cl-card-title mt-1">Change Password</h2>
                </div>
                <input name="current_password" type="password" placeholder="Current password">
                <input name="password" type="password" placeholder="New password">
                <input name="password_confirmation" type="password" placeholder="Confirm password">
                <button class="cl-button-primary" type="submit">Change password</button>
            </form>

            <form method="POST" action="{{ route('profile.notifications') }}" class="cl-form-card space-y-5">
                @csrf
                @method('PUT')
                <div>
                    <p class="cl-kicker">Preferences</p>
                    <h2 class="cl-card-title mt-1">Notification Preferences</h2>
                </div>
                <label class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white/60 px-3 py-2 text-sm font-semibold dark:border-slate-800 dark:bg-slate-950/30">
                    <input type="checkbox" name="email_reports" value="1" @checked(data_get($preferences, 'email_reports'))>
                    Email reports
                </label>
                <label class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white/60 px-3 py-2 text-sm font-semibold dark:border-slate-800 dark:bg-slate-950/30">
                    <input type="checkbox" name="security_alerts" value="1" @checked(data_get($preferences, 'security_alerts'))>
                    Security alerts
                </label>

                <fieldset class="space-y-3">
                    <legend class="text-sm font-bold text-slate-950 dark:text-white">Appearance</legend>
                    <div class="grid gap-3 sm:grid-cols-3">
                        @foreach (['system' => 'System', 'light' => 'Light', 'dark' => 'Dark'] as $value => $label)
                            <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white/70 px-3 py-2 text-sm font-semibold dark:border-slate-800 dark:bg-slate-950/40">
                                <input type="radio" name="appearance" value="{{ $value }}" @checked($appearance === $value)>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <button class="cl-button-primary" type="submit">Save preferences</button>
            </form>
        </div>

        <section class="cl-card">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="cl-kicker">Audit Trail</p>
                    <h2 class="cl-card-title mt-1">Account Activity</h2>
                </div>
                <p class="text-sm cl-muted">Recent profile and security events</p>
            </div>
            <ul class="mt-4 divide-y divide-slate-200 overflow-hidden rounded-xl border border-slate-200 dark:divide-slate-800 dark:border-slate-800">
                @forelse ($activities as $activity)
                    <li class="flex flex-col gap-1 bg-white/60 px-4 py-3 text-sm dark:bg-slate-950/20 sm:flex-row sm:items-center sm:justify-between">
                        <span class="font-semibold">{{ $activity->event }}</span>
                        <span class="cl-muted">{{ $activity->created_at->diffForHumans() }}</span>
                    </li>
                @empty
                    <li class="bg-white/60 px-4 py-3 text-sm cl-muted dark:bg-slate-950/20">No activity recorded yet.</li>
                @endforelse
            </ul>
        </section>
    </section>
</x-layouts.app>
