<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'CivicLens' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-950 antialiased dark:bg-slate-950 dark:text-slate-100">
    @php
        $navLink = 'rounded-md px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-950 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white';
    @endphp

    <div class="flex min-h-screen flex-col">
    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 px-4 py-3 shadow-sm shadow-slate-900/5 backdrop-blur dark:border-slate-800 dark:bg-slate-950/90 sm:px-6">
        <nav class="mx-auto flex max-w-7xl flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <a class="inline-flex items-center gap-3 rounded-md font-bold tracking-tight text-blue-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700 dark:text-blue-200" href="{{ route('public.home') }}">
                <span class="grid size-9 place-items-center rounded-lg bg-blue-900 text-sm font-bold text-white dark:bg-blue-300 dark:text-blue-950">CL</span>
                <span>CivicLens</span>
            </a>
            <div class="flex flex-wrap gap-1">
                <a class="{{ $navLink }}" href="{{ route('public.home') }}">Public</a>
                @auth
                    <a class="{{ $navLink }}" href="{{ route('dashboard') }}">Dashboard</a>
                    @can('create', App\Models\CitizenReport::class)
                        <a class="{{ $navLink }}" href="{{ route('citizen.reports.index') }}">My Reports</a>
                    @endcan
                    @can('viewAny', App\Models\CitizenReport::class)
                        <a class="{{ $navLink }}" href="{{ route('admin.citizen-reports.index') }}">Citizen Reports</a>
                    @endcan
                    <a class="{{ $navLink }}" href="{{ route('profile.show') }}">Profile</a>
                    @can('viewAny', App\Models\User::class)
                        <a class="{{ $navLink }}" href="{{ route('admin.users.index') }}">Users</a>
                    @endcan
                    @can('viewAny', App\Models\Agency::class)
                        <a class="{{ $navLink }}" href="{{ route('admin.agencies.index') }}">Agencies</a>
                    @endcan
                    @if (auth()->user()?->hasRole(config('civiclens.roles.admin')) || auth()->user()?->hasPermission(config('civiclens.permissions.search_manage')))
                        <a class="{{ $navLink }}" href="{{ route('admin.search.index') }}">Search</a>
                    @endif
                    @can('viewAny', App\Models\AnalyticsReport::class)
                        <a class="{{ $navLink }}" href="{{ route('admin.analytics.index') }}">Analytics</a>
                    @endcan
                    @can('viewAny', App\Models\IntelligenceIndicator::class)
                        <a class="{{ $navLink }}" href="{{ route('admin.intelligence.index') }}">Intelligence</a>
                    @endcan
                    @can('viewAny', App\Models\Document::class)
                        <a class="{{ $navLink }}" href="{{ route('admin.documents.index') }}">Documents</a>
                    @endcan
                    @can('viewAny', App\Models\Organization::class)
                        <a class="{{ $navLink }}" href="{{ route('admin.contractors.organizations.index') }}">Contractors</a>
                    @endcan
                    @can('viewAny', App\Models\Project::class)
                        <a class="{{ $navLink }}" href="{{ route('admin.projects.index') }}">Projects</a>
                    @endcan
                    @can('viewAny', App\Models\Budget::class)
                        <a class="{{ $navLink }}" href="{{ route('admin.finance.budgets.index') }}">Finance</a>
                    @endcan
                    @can('viewAny', App\Models\Tender::class)
                        <a class="{{ $navLink }}" href="{{ route('admin.procurement.tenders.index') }}">Procurement</a>
                    @endcan
                    @can('viewAny', App\Models\Country::class)
                        <a class="{{ $navLink }}" href="{{ route('admin.geography.countries.index') }}">Geography</a>
                    @endcan
                @else
                    <a class="{{ $navLink }}" href="{{ route('login') }}">Login</a>
                    <a class="rounded-md bg-blue-900 px-3 py-2 text-sm font-semibold text-white transition hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700 dark:bg-blue-300 dark:text-blue-950 dark:hover:bg-blue-200" href="{{ route('register') }}">Register</a>
                @endauth
            </div>
        </nav>
    </header>

    <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 shadow-sm dark:border-emerald-900/60 dark:bg-emerald-950 dark:text-emerald-100">
                {{ session('status') }}
            </div>
        @endif

        {{ $slot }}
    </main>

    <footer class="border-t border-slate-200 bg-white/70 px-4 py-5 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-400 sm:px-6">
        <div class="mx-auto flex max-w-7xl flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <span>CivicLens {{ config('app.version') }}</span>
            <span>Release candidate metadata: {{ config('app.commit') }}</span>
        </div>
    </footer>
    </div>
</body>
</html>
