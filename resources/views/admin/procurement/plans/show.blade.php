<x-layouts.app :title="$plan->title">
    <section class="space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase text-emerald-700">{{ $plan->plan_number }}</p>
            <h1 class="text-3xl font-bold">{{ $plan->title }}</h1>
            <p class="mt-2 text-slate-500">{{ str($plan->status)->headline() }} · {{ $plan->agency?->name }} · {{ number_format((float) $plan->estimated_value, 2) }}</p>
        </div>
        @if ($plan->status !== 'approved')
            <form method="POST" action="{{ route('admin.procurement.plans.approve', $plan) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="approval_notes" value="Approved from plan detail.">
                <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Approve plan</button>
            </form>
        @endif
        <section class="space-y-3">
            <h2 class="text-xl font-semibold">Approval Timeline</h2>
            @foreach ($plan->activities as $activity)
                <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-900">{{ str($activity->event)->headline() }} · {{ $activity->created_at->toDayDateTimeString() }}</div>
            @endforeach
        </section>
    </section>
</x-layouts.app>
