@props([
    'title' => 'CivicLens',
    'shell' => null,
    'immersive' => false,
])

@php
    use Illuminate\Support\Facades\Route;
    $user = auth()->user();
    $user?->loadMissing('roles.permissions');
    $appearance = $user?->notification_preferences['appearance'] ?? 'system';
    $roleSlugs = $user?->roles->pluck('slug') ?? collect();
    $isAdministrator = $roleSlugs->contains(config('civiclens.roles.admin'));
    $isStaff = ! $isAdministrator && $roleSlugs->contains(config('civiclens.roles.staff'));
    $isCitizen = ! $isAdministrator && ! $isStaff && $user !== null;
    $resolvedShell = $shell ?: ($isAdministrator ? 'administrator' : ($isStaff ? 'staff' : ($isCitizen ? 'citizen' : 'public')));
    $avatarUrl = $user?->avatar_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($user->avatar_path) : null;
    $avatarInitial = mb_strtoupper(mb_substr($user?->name ?? 'U', 0, 1));
    $shellLabel = match ($resolvedShell) {
        'administrator' => 'Administration',
        'staff' => 'Staff workspace',
        'citizen' => 'My CivicLens',
        default => 'Public evidence',
    };
    $shellDescription = match ($resolvedShell) {
        'administrator' => 'Govern the platform, access, evidence, and integrity controls.',
        'staff' => 'Manage authorized delivery work and evidence review.',
        'citizen' => 'Follow your districts, reports, and approved public changes.',
        default => 'Explore verified public delivery records.',
    };
    $publicLinks = [
        ['label' => 'About', 'route' => route('public.about')],
        ['label' => 'Projects', 'route' => route('public.projects.index')],
        ['label' => 'Agencies', 'route' => route('public.agencies.index')],
        ['label' => 'Contractors', 'route' => route('public.contractors.index')],
        ['label' => 'Procurement', 'route' => route('public.procurement.index')],
        ['label' => 'Documents', 'route' => route('public.documents.index')],
        ['label' => 'Search', 'route' => route('public.search')],
    ];
    // Grouped by what an operator is doing rather than listed flat. Acquisition
    // is its own area because its screens are read together: a finding is only
    // worth reading once the health beside it says the crawl actually ran.
    //
    // Route::has guards let these screens arrive on separate branches without
    // the navigation breaking in between.
    $canSources = $user?->can('viewAny', App\Models\SourcePublisher::class) === true;
    $workspaceGroups = [
        [
            'label' => null,
            'links' => [
                ['label' => 'Dashboard', 'route' => route('dashboard'), 'show' => true],
                ['label' => 'Public portal', 'route' => route('public.home'), 'show' => true],
            ],
        ],
        [
            'label' => 'Acquisition',
            'links' => [
                ['label' => 'Source registry', 'route' => route('admin.sources.index'), 'show' => $canSources],
                ['label' => 'Findings', 'route' => Route::has('admin.sources.findings') ? route('admin.sources.findings') : '', 'show' => $canSources && Route::has('admin.sources.findings')],
                ['label' => 'Extraction', 'route' => Route::has('admin.sources.extraction') ? route('admin.sources.extraction') : '', 'show' => $canSources && Route::has('admin.sources.extraction')],
                ['label' => 'Review queue', 'route' => Route::has('admin.sources.review') ? route('admin.sources.review') : '', 'show' => $canSources && Route::has('admin.sources.review')],
            ],
        ],
        [
            'label' => 'Records',
            'links' => [
                ['label' => 'Projects', 'route' => route('admin.projects.index'), 'show' => $user?->can('viewAny', App\Models\Project::class) === true],
                ['label' => 'Project map', 'route' => route('admin.projects.map'), 'show' => $user?->can('viewAny', App\Models\Project::class) === true],
                ['label' => 'Agencies', 'route' => route('admin.agencies.index'), 'show' => $user?->can('viewAny', App\Models\Agency::class) === true],
                ['label' => 'Procurement', 'route' => route('admin.procurement.tenders.index'), 'show' => $user?->can('viewAny', App\Models\Tender::class) === true],
                ['label' => 'Contractors', 'route' => route('admin.contractors.organizations.index'), 'show' => $user?->can('viewAny', App\Models\Organization::class) === true],
                ['label' => 'Documents', 'route' => route('admin.documents.index'), 'show' => $user?->can('viewAny', App\Models\Document::class) === true],
            ],
        ],
        [
            'label' => 'Insight',
            'links' => [
                ['label' => 'Analytics', 'route' => route('admin.analytics.index'), 'show' => $user?->can('viewAny', App\Models\AnalyticsReport::class) === true],
                ['label' => 'Intelligence', 'route' => route('admin.intelligence.index'), 'show' => $user?->can('viewAny', App\Models\IntelligenceIndicator::class) === true],
            ],
        ],
        [
            'label' => 'Community',
            'links' => [
                ['label' => 'Citizen reports', 'route' => route('admin.citizen-reports.index'), 'show' => $user?->can('viewAny', App\Models\CitizenReport::class) === true],
                ['label' => 'Staff requests', 'route' => route('admin.change-requests.index'), 'show' => $isAdministrator || $isStaff],
            ],
        ],
        [
            'label' => 'Administration',
            'links' => [
                ['label' => 'Users', 'route' => route('admin.users.index'), 'show' => $user?->can('viewAny', App\Models\User::class) === true],
                ['label' => 'Settings', 'route' => route('settings.show'), 'show' => true],
            ],
        ],
    ];
    $citizenLinks = [
        ['label' => 'My overview', 'route' => route('dashboard')],
        ['label' => 'Explore my district', 'route' => route('public.projects.index')],
        ['label' => 'My reports', 'route' => route('citizen.reports.index')],
        ['label' => 'Submit a report', 'route' => route('public.reports.create')],
        ['label' => 'Search evidence', 'route' => route('public.search')],
        ['label' => 'Preferences', 'route' => route('settings.show')],
    ];
@endphp

<!DOCTYPE html>
<html lang="en" data-appearance="{{ $appearance }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
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
    <div class="cl-app-shell cl-app-shell--{{ $resolvedShell }}" data-role-shell="{{ $resolvedShell }}">
        @unless ($immersive)
            <header class="cl-topbar">
                <nav class="cl-topbar-inner" aria-label="Primary navigation">
                    <a class="cl-brand" href="{{ route('public.home') }}">
                        <span class="cl-brand-mark" aria-hidden="true">CL</span>
                        <span>
                            <span class="block text-sm font-bold">CivicLens</span>
                            <span class="cl-shell-label">{{ $shellLabel }}</span>
                        </span>
                    </a>

                    @if ($resolvedShell === 'public')
                        <div class="cl-nav-scroll">
                            @foreach ($publicLinks as $link)
                                <a class="cl-nav-link" href="{{ $link['route'] }}">{{ $link['label'] }}</a>
                            @endforeach
                            @if ($isCitizen)
                                <a class="cl-nav-link" href="{{ route('citizen.reports.index') }}">My Reports</a>
                            @endif
                            @if ($user?->can('viewAny', App\Models\CitizenReport::class) === true)
                                <a class="cl-nav-link" href="{{ route('admin.citizen-reports.index') }}">Citizen Reports</a>
                            @endif
                        </div>
                    @else
                        <p class="cl-topbar-context">{{ $shellDescription }}</p>
                        <a class="cl-nav-link hidden sm:inline-flex" href="{{ route('public.home') }}">View public portal</a>
                    @endif

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
                        <a class="cl-nav-link" href="{{ route('login') }}">Login</a>
                        <a class="cl-button-primary" href="{{ route('register') }}">Register</a>
                    @endauth
                </nav>
            </header>
        @endunless

        <div class="cl-shell-grid {{ $resolvedShell !== 'public' ? 'cl-shell-grid--workspace' : '' }} {{ $immersive ? 'cl-shell-grid--immersive' : '' }}">
            @if ($resolvedShell !== 'public')
                <aside class="cl-role-rail" aria-label="{{ $shellLabel }} navigation">
                    <div class="cl-role-rail__heading">
                        <span>{{ $shellLabel }}</span>
                        <small>{{ $resolvedShell === 'administrator' ? 'Control plane' : ($resolvedShell === 'staff' ? 'Operations' : 'Citizen space') }}</small>
                    </div>
                    <nav class="cl-role-nav">
                        @if ($resolvedShell === 'citizen')
                            @foreach ($citizenLinks as $link)
                                @php($isActive = url()->current() === $link['route'] || str_starts_with(url()->current(), rtrim($link['route'], '/').'/'))
                                <a class="cl-role-nav__link {{ $isActive ? 'is-active' : '' }}" href="{{ $link['route'] }}" @if ($isActive) aria-current="page" @endif>
                                    <span aria-hidden="true"></span>{{ $link['label'] }}
                                </a>
                            @endforeach
                        @else
                            @foreach ($workspaceGroups as $group)
                                @php($visible = array_filter($group['links'], fn (array $link): bool => ($link['show'] ?? true) === true))
                                @if ($visible !== [])
                                    @if ($group['label'] !== null)
                                        <p class="cl-role-nav__group">{{ $group['label'] }}</p>
                                    @endif
                                    @foreach ($visible as $link)
                                        @php($isActive = url()->current() === $link['route'] || str_starts_with(url()->current(), rtrim($link['route'], '/').'/'))
                                        <a class="cl-role-nav__link {{ $isActive ? 'is-active' : '' }}" href="{{ $link['route'] }}" @if ($isActive) aria-current="page" @endif>
                                            <span aria-hidden="true"></span>{{ $link['label'] }}
                                        </a>
                                    @endforeach
                                @endif
                            @endforeach
                        @endif
                    </nav>
                </aside>
            @endif

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

        <footer class="cl-footer">
            <span>CivicLens {{ config('app.version') }}</span>
            <span>Release metadata: {{ config('app.commit') }}</span>
        </footer>
    </div>
</body>
</html>
