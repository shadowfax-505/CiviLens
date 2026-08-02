<x-layouts.app title="Procurement Plans">
    <section class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold uppercase text-emerald-700">Planning</p>
                <h1 class="text-3xl font-bold">Procurement Plans</h1>
            </div>
            @can('create', App\Models\ProcurementPlan::class)
                <a class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-950" href="{{ route('admin.procurement.plans.create') }}">Create plan</a>
            @endcan
        </div>
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            @forelse ($plans as $plan)
                <a class="block border-b border-slate-100 p-5 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800" href="{{ route('admin.procurement.plans.show', $plan) }}">
                    <span class="font-semibold">{{ $plan->plan_number }} · {{ $plan->title }}</span>
                    <span class="ml-2 text-sm text-slate-500">{{ str($plan->status)->headline() }} · {{ $plan->agency?->name }}</span>
                </a>
            @empty
                <p class="p-5 text-slate-500">No procurement plans are available.</p>
            @endforelse
        </div>
        {{ $plans->links() }}
    </section>
</x-layouts.app>
