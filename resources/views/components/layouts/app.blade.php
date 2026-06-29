<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'CivicLens' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-950 dark:bg-slate-950 dark:text-slate-100">
    <header class="border-b border-slate-200 bg-white/90 px-6 py-4 dark:border-slate-800 dark:bg-slate-900">
        <nav class="mx-auto flex max-w-6xl items-center justify-between">
            <a class="font-semibold tracking-tight" href="/">CivicLens</a>
            <div class="flex gap-4 text-sm">
                @auth
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <a href="{{ route('profile.show') }}">Profile</a>
                    @can('viewAny', App\Models\User::class)
                        <a href="{{ route('admin.users.index') }}">Users</a>
                    @endcan
                    @can('viewAny', App\Models\Agency::class)
                        <a href="{{ route('admin.agencies.index') }}">Agencies</a>
                    @endcan
                    @can('viewAny', App\Models\Project::class)
                        <a href="{{ route('admin.projects.index') }}">Projects</a>
                    @endcan
                    @can('viewAny', App\Models\Budget::class)
                        <a href="{{ route('admin.finance.budgets.index') }}">Finance</a>
                    @endcan
                    @can('viewAny', App\Models\Tender::class)
                        <a href="{{ route('admin.procurement.tenders.index') }}">Procurement</a>
                    @endcan
                    @can('viewAny', App\Models\Country::class)
                        <a href="{{ route('admin.geography.countries.index') }}">Geography</a>
                    @endcan
                @else
                    <a href="{{ route('login') }}">Login</a>
                    <a href="{{ route('register') }}">Register</a>
                @endauth
            </div>
        </nav>
    </header>

    <main class="mx-auto max-w-6xl px-6 py-10">
        @if (session('status'))
            <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-900">
                {{ session('status') }}
            </div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
