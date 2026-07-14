@php
    $user = auth()->user();
    $appearance = $user?->notification_preferences['appearance'] ?? 'system';
    $navLink = 'cl-nav-link';
    $canViewAnalytics = $user?->can('viewAny', App\Models\AnalyticsReport::class) === true;
    $canViewIntelligence = $user?->can('viewAny', App\Models\IntelligenceIndicator::class) === true;
    $avatarUrl = $user?->avatar_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($user->avatar_path) : null;
    $avatarInitial = mb_strtoupper(mb_substr($user?->name ?? 'U', 0, 1));
    $isWorkspaceUser = $user?->hasRole(config('civiclens.roles.admin')) === true || $user?->hasRole(config('civiclens.roles.staff')) === true;
    $projectsHref = $isWorkspaceUser ? route('admin.projects.index') : route('public.projects.index');
    $agenciesHref = $isWorkspaceUser ? route('admin.agencies.index') : route('public.agencies.index');
    $contractorsHref = $isWorkspaceUser ? route('admin.contractors.organizations.index') : route('public.contractors.index');
    $procurementHref = $isWorkspaceUser ? route('admin.procurement.tenders.index') : route('public.procurement.index');
    $documentsHref = $isWorkspaceUser ? route('admin.documents.index') : route('public.documents.index');
    $projectMapHref = $isWorkspaceUser ? route('admin.projects.map') : route('public.projects.index');
    $searchHref = $isWorkspaceUser ? route('admin.search.index') : route('public.search');
@endphp

<!DOCTYPE html>
<html lang="en" data-appearance="{{ $appearance }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>{{ $title ?? 'CivicLens' }}</title>
    <script>
        (() => {
            const mode = document.documentElement.dataset.appearance || 'system';
            const dark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
            document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans text-slate-950 antialiased dark:text-slate-100">
    <div class="cl-app-shell">
        <header class="cl-topbar">
            <nav class="cl-topbar-inner" aria-label="Primary navigation">
                <a class="cl-brand" href="{{ route('public.about') }}">
                    <span class="cl-brand-mark">CL</span>
                    <span>
                        <span class="block text-sm font-bold">CivicLens</span>
                        <span class="hidden text-[11px] font-semibold uppercase text-slate-500 dark:text-slate-400 sm:block">Public integrity platform</span>
                    </span>
                </a>

                <div class="cl-nav-scroll">
                    <a class="{{ $navLink }}" href="{{ route('public.about') }}">About</a>
                    <a class="{{ $navLink }}" href="{{ $projectsHref }}">Projects</a>
                    <a class="{{ $navLink }}" href="{{ $agenciesHref }}">Agencies</a>
                    <a class="{{ $navLink }}" href="{{ $contractorsHref }}">Contractors</a>
                    <a class="{{ $navLink }}" href="{{ $procurementHref }}">Procurement</a>
                    <a class="{{ $navLink }}" href="{{ $documentsHref }}">Documents</a>
                    <a class="{{ $navLink }}" href="{{ $searchHref }}">Search</a>
                    @auth
                        <a class="{{ $navLink }}" href="{{ route('dashboard') }}">Dashboard</a>
                        @if ($isWorkspaceUser)
                            <a class="{{ $navLink }}" href="{{ $projectMapHref }}">Project Map</a>
                            <a class="{{ $navLink }}" href="{{ route('admin.change-requests.index') }}">Staff Requests</a>
                        @endif
                        @can('viewDashboard', App\Models\CitizenReport::class)
                            <a class="{{ $navLink }}" href="{{ route('citizen.reports.index') }}">My Reports</a>
                        @endcan
                        @can('viewAny', App\Models\CitizenReport::class)
                            <a class="{{ $navLink }}" href="{{ route('admin.citizen-reports.index') }}">Citizen Reports</a>
                        @endcan
                        @can('viewAny', App\Models\User::class)
                            <a class="{{ $navLink }}" href="{{ route('admin.users.index') }}">Users</a>
                        @endcan
                        @if ($canViewAnalytics)
                            <a class="{{ $navLink }}" href="{{ route('admin.analytics.index') }}">Analytics</a>
                        @endif
                        @if ($canViewIntelligence)
                            <a class="{{ $navLink }}" href="{{ route('admin.intelligence.index') }}">Intelligence</a>
                        @endif
                        @can('viewAny', App\Models\Budget::class)
                            <a class="{{ $navLink }}" href="{{ route('admin.finance.budgets.index') }}">Finance</a>
                        @endcan
                    @endauth
                </div>

                @auth
                    <div class="cl-user-menu" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                        <button class="cl-avatar-button" type="button" @click="open = ! open" :aria-expanded="open.toString()" aria-haspopup="menu" aria-label="Open account menu">
                            @if ($avatarUrl)
                                <img src="{{ $avatarUrl }}" alt="" class="cl-avatar-image">
                            @else
                                <span class="cl-avatar-initial">{{ $avatarInitial }}</span>
                            @endif
                        </button>
                        <div class="cl-account-menu" x-cloak x-show="open" x-transition.origin.top.right role="menu">
                            <div class="cl-account-menu-header">
                                <p class="font-bold text-slate-950 dark:text-white">{{ $user?->name }}</p>
                                <p class="text-xs cl-muted">{{ $user?->email }}</p>
                            </div>
                            <a class="cl-account-menu-item" href="{{ route('settings.show') }}" role="menuitem">Settings</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="cl-account-menu-item w-full text-left" type="submit" role="menuitem">Logout</button>
                            </form>
                        </div>
                    </div>
                @else
                    <a class="{{ $navLink }}" href="{{ route('login') }}">Login</a>
                    <a class="cl-button-primary" href="{{ route('register') }}">Register</a>
                @endauth
            </nav>
        </header>

        <div class="cl-shell-grid">
            <main class="cl-main">
                @if (session('status'))
                    <div class="cl-alert" role="status">
                        <span class="cl-status-dot"></span>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>

        {{-- Debug: capture client-side form submits for troubleshooting --}}
        <script>
            (function () {
                document.addEventListener('submit', function (e) {
                    try {
                        const form = e.target;
                        if (!(form instanceof HTMLFormElement)) return;
                        // log brief details to the console to help reproduction
                        console.info('Form submit captured', {action: form.action, method: form.method, id: form.id || null});
                        // send a lightweight beacon to /_debug/form-submit for server-side logging
                        if (navigator.sendBeacon) {
                            var payload = JSON.stringify({action: form.action, method: form.method});
                            navigator.sendBeacon('/_debug/form-submit', payload);
                        } else {
                            fetch('/_debug/form-submit', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({action: form.action, method: form.method})}).catch(()=>{});
                        }
                    } catch (err) {
                        // ignore
                    }
                }, true);
            })();
        </script>

        <footer class="cl-footer">
            <span>CivicLens {{ config('app.version') }}</span>
            <span>Release metadata: {{ config('app.commit') }}</span>
        </footer>
    </div>
</body>
</html>
