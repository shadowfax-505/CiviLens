<x-layouts.app title="Public Contractors">
    <section class="space-y-6">
        <h1 class="text-3xl font-bold">Public Contractors</h1>
        <form method="GET" class="grid gap-3 md:grid-cols-4">
            <input name="q" value="{{ $filters['q'] ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950" placeholder="Search contractors">
            <select name="organization_company_type_id"><option value="">All company types</option>@foreach ($companyTypes as $type)<option value="{{ $type->id }}" @selected(($filters['organization_company_type_id'] ?? null) == $type->id)>{{ $type->name }}</option>@endforeach</select>
            <select name="organization_industry_id"><option value="">All industries</option>@foreach ($industries as $industry)<option value="{{ $industry->id }}" @selected(($filters['organization_industry_id'] ?? null) == $industry->id)>{{ $industry->name }}</option>@endforeach</select>
            <button class="rounded-lg bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950">Search</button>
        </form>
        <div class="grid gap-4 md:grid-cols-2">
            @forelse ($contractors as $organization)
                <article class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <a class="text-lg font-semibold" href="{{ route('public.contractors.show', $organization) }}">{{ $organization->legal_name }}</a>
                    <p class="mt-1 text-sm text-slate-500">{{ $organization->companyType?->name }} · {{ $organization->industry?->name }}</p>
                </article>
            @empty
                <p class="text-slate-500">No public contractors match the current filters.</p>
            @endforelse
        </div>
        {{ $contractors->links() }}
    </section>
</x-layouts.app>
