<x-layouts.app :title="$organization->legal_name.' - CivicLens'">
    @php($mapLocation = ($organization->headquarters_latitude !== null && $organization->headquarters_longitude !== null) ? $organization : $organization->branches->first(fn ($branch) => $branch->latitude !== null && $branch->longitude !== null))
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Contractor Intelligence</p>
            <h1 class="text-3xl font-bold">{{ $organization->legal_name }}</h1>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $organization->registration_number }} · {{ $organization->industry?->name }} · {{ ucfirst($organization->status) }}</p>
            @if (auth()->user()?->hasRole(config('civiclens.roles.staff')))
                <a class="mt-4 inline-flex rounded-full border border-emerald-300 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-300 dark:hover:bg-emerald-950" href="{{ route('admin.change-requests.create', ['module' => 'contractors', 'subject_type' => App\Models\Organization::class, 'subject_id' => $organization->id, 'subject_label' => $organization->legal_name, 'subject_url' => route('admin.contractors.organizations.show', $organization)]) }}">Request change</a>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            @can('update', $organization)
                <a href="{{ route('admin.contractors.organizations.edit', $organization) }}" class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700">Edit</a>
                @if ($organization->archived_at)
                    <form method="POST" action="{{ route('admin.contractors.organizations.restore', $organization) }}">@csrf @method('PATCH')<button class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Restore</button></form>
                @else
                    <form method="POST" action="{{ route('admin.contractors.organizations.archive', $organization) }}">@csrf @method('PATCH')<button class="rounded bg-amber-700 px-4 py-2 text-sm font-semibold text-white">Archive</button></form>
                @endif
            @endcan
        </div>
    </div>

    @if ($mapLocation)
        <div class="mt-8 rounded-xl border bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <x-leaflet-static-map :label="$organization->legal_name.' office location'" :lat="$mapLocation->headquarters_latitude ?? $mapLocation->latitude" :lng="$mapLocation->headquarters_longitude ?? $mapLocation->longitude" :markers="$organization->branches->filter(fn ($branch) => $branch->latitude !== null && $branch->longitude !== null)->map(fn ($branch) => ['lat' => $branch->latitude, 'lng' => $branch->longitude, 'label' => $branch->address])->values()->all()" />
        </div>
    @endif

    <div class="mt-8 grid gap-4 md:grid-cols-4">
        @foreach (($scorecard ?? [
            'overall_contractor_score' => 0,
            'risk_score' => 0,
            'compliance_score' => 0,
            'delivery_score' => 0,
            'financial_score' => 0,
            'quality_score' => 0,
            'experience_score' => 0,
            'contract_success_rate' => 0,
        ]) as $label => $value)
            @if (str_ends_with($label, '_score') || $label === 'contract_success_rate')
                <div class="rounded-xl border bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ str_replace('_', ' ', $label) }}</div>
                    <div class="mt-2 text-2xl font-bold">{{ $value }}%</div>
                </div>
            @endif
        @endforeach
    </div>

    <section class="mt-8 rounded-xl border bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-xl font-semibold">Contractor Profile</h2>
        @if ($organization->profile)
            <div class="mt-4 grid gap-3 text-sm md:grid-cols-4">
                <div><span class="text-slate-500">Category</span><div class="font-semibold">{{ $organization->profile->category?->name }}</div></div>
                <div><span class="text-slate-500">Classification</span><div class="font-semibold">{{ $organization->profile->classification?->name }}</div></div>
                <div><span class="text-slate-500">Registration</span><div class="font-semibold">{{ $organization->profile->registrationStatus?->name }}</div></div>
                <div><span class="text-slate-500">Risk</span><div class="font-semibold">{{ $organization->profile->riskLevel?->name }}</div></div>
            </div>
        @else
            <p class="mt-3 text-sm text-slate-500">No contractor profile exists yet. Add one below to activate intelligence scoring.</p>
        @endif

        <form method="POST" action="{{ route('admin.contractors.organizations.profile.store', $organization) }}" class="mt-6 grid gap-3 md:grid-cols-4">
            @csrf
            <select name="contractor_category_id" class="rounded border px-3 py-2 text-slate-950">
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('contractor_category_id', $organization->profile?->contractor_category_id) == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <select name="contractor_classification_id" class="rounded border px-3 py-2 text-slate-950">
                @foreach ($classifications as $classification)
                    <option value="{{ $classification->id }}" @selected(old('contractor_classification_id', $organization->profile?->contractor_classification_id) == $classification->id)>{{ $classification->name }}</option>
                @endforeach
            </select>
            <select name="contractor_registration_status_id" class="rounded border px-3 py-2 text-slate-950">
                @foreach ($registrationStatuses as $status)
                    <option value="{{ $status->id }}" @selected(old('contractor_registration_status_id', $organization->profile?->contractor_registration_status_id) == $status->id)>{{ $status->name }}</option>
                @endforeach
            </select>
            <select name="contractor_risk_level_id" class="rounded border px-3 py-2 text-slate-950">
                @foreach ($riskLevels as $riskLevel)
                    <option value="{{ $riskLevel->id }}" @selected(old('contractor_risk_level_id', $organization->profile?->contractor_risk_level_id) == $riskLevel->id)>{{ $riskLevel->name }}</option>
                @endforeach
            </select>
            <label class="text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $organization->profile?->is_active ?? true))> Active</label>
            <label class="text-sm"><input type="checkbox" name="is_suspended" value="1" @checked(old('is_suspended', $organization->profile?->is_suspended ?? false))> Suspended</label>
            <label class="text-sm"><input type="checkbox" name="is_blacklisted" value="1" @checked(old('is_blacklisted', $organization->profile?->is_blacklisted ?? false))> Blacklisted</label>
            <label class="text-sm"><input type="checkbox" name="is_public" value="1" @checked(old('is_public', $organization->profile?->is_public ?? true))> Public</label>
            <div class="md:col-span-4"><button class="rounded bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-950">Save contractor profile</button></div>
        </form>
    </section>

    <section class="mt-8 rounded-xl border bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-xl font-semibold">Timeline</h2>
        <div class="mt-4 space-y-3">
            @forelse ($organization->profile?->activities ?? [] as $activity)
                <div class="rounded border p-3 text-sm dark:border-slate-800">
                    <div class="font-semibold">{{ $activity->event }}</div>
                    <div class="text-slate-500">{{ $activity->description }} · {{ $activity->created_at->format('Y-m-d H:i') }}</div>
                </div>
            @empty
                <p class="text-sm text-slate-500">No contractor activities recorded yet.</p>
            @endforelse
        </div>
    </section>
</x-layouts.app>
