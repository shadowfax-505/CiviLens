<x-layouts.app :title="$organization->legal_name">
    @php($mapLocation = ($organization->headquarters_latitude !== null && $organization->headquarters_longitude !== null) ? $organization : $organization->branches->first(fn ($branch) => $branch->latitude !== null && $branch->longitude !== null))
    <section class="space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase text-emerald-700">Contractor Profile</p>
            <h1 class="mt-2 text-3xl font-bold">{{ $organization->legal_name }}</h1>
            <p class="mt-2 text-slate-600 dark:text-slate-300">{{ $organization->trade_name }}</p>
            @if (auth()->user()?->hasRole(config('civiclens.roles.staff')))
                <a class="mt-4 inline-flex rounded-full border border-emerald-300 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-300 dark:hover:bg-emerald-950" href="{{ route('admin.change-requests.create', ['module' => 'contractors', 'subject_type' => App\Models\Organization::class, 'subject_id' => $organization->id, 'subject_label' => $organization->legal_name, 'subject_url' => route('public.contractors.show', $organization)]) }}">Request change</a>
            @endif
        </div>
        <dl class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><dt class="text-xs text-slate-500">Registration</dt><dd>{{ $organization->registration_number }}</dd></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><dt class="text-xs text-slate-500">Company Type</dt><dd>{{ $organization->companyType?->name }}</dd></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><dt class="text-xs text-slate-500">Industry</dt><dd>{{ $organization->industry?->name }}</dd></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><dt class="text-xs text-slate-500">Category</dt><dd>{{ $organization->profile?->category?->name }}</dd></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><dt class="text-xs text-slate-500">Classification</dt><dd>{{ $organization->profile?->classification?->name }}</dd></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><dt class="text-xs text-slate-500">Status</dt><dd>{{ $organization->profile?->registrationStatus?->name ?? str($organization->status)->headline() }}</dd></div>
        </dl>
        @if ($mapLocation)
            <x-leaflet-static-map :label="$organization->legal_name.' office location'" :lat="$mapLocation->headquarters_latitude ?? $mapLocation->latitude" :lng="$mapLocation->headquarters_longitude ?? $mapLocation->longitude" :markers="$organization->branches->filter(fn ($branch) => $branch->latitude !== null && $branch->longitude !== null)->map(fn ($branch) => ['lat' => $branch->latitude, 'lng' => $branch->longitude, 'label' => $branch->address])->values()->all()" />
        @endif
        @if ($organization->branches->isNotEmpty())
            <section class="cl-card">
                <h2 class="cl-card-title">Public offices</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach ($organization->branches as $branch)
                        <li>{{ $branch->address }}@if ($branch->phone) · {{ $branch->phone }}@endif</li>
                    @endforeach
                </ul>
            </section>
        @endif
    </section>
</x-layouts.app>
