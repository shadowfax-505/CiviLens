<x-layouts.app :title="$organization->legal_name">
    <section class="space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase text-emerald-700">Contractor Profile</p>
            <h1 class="mt-2 text-3xl font-bold">{{ $organization->legal_name }}</h1>
            <p class="mt-2 text-slate-600 dark:text-slate-300">{{ $organization->trade_name }}</p>
        </div>
        <dl class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><dt class="text-xs text-slate-500">Registration</dt><dd>{{ $organization->registration_number }}</dd></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><dt class="text-xs text-slate-500">Company Type</dt><dd>{{ $organization->companyType?->name }}</dd></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><dt class="text-xs text-slate-500">Industry</dt><dd>{{ $organization->industry?->name }}</dd></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><dt class="text-xs text-slate-500">Category</dt><dd>{{ $organization->profile?->category?->name }}</dd></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><dt class="text-xs text-slate-500">Classification</dt><dd>{{ $organization->profile?->classification?->name }}</dd></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><dt class="text-xs text-slate-500">Status</dt><dd>{{ $organization->profile?->registrationStatus?->name ?? str($organization->status)->headline() }}</dd></div>
        </dl>
    </section>
</x-layouts.app>
