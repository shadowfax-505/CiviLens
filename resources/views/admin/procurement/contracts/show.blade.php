<x-layouts.app :title="'Contract Detail - CivicLens'">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Contract Detail</p>
            <h1 class="text-3xl font-bold">{{ $contract->title }}</h1>
            <p class="mt-2 text-slate-600 dark:text-slate-300">{{ $contract->contract_number }} | {{ $contract->bidSubmission?->bidderOrganization?->name }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.procurement.tenders.show', $contract->award->tender) }}" class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700">Back to tender</a>
            @if ($contract->status !== 'closed')
                <form method="POST" action="{{ route('admin.procurement.contracts.close', $contract) }}">@csrf @method('PATCH')<input type="hidden" name="notes" value="Closed from contract dashboard."><button class="rounded bg-slate-700 px-4 py-2 text-sm font-semibold text-white">Close contract</button></form>
            @endif
        </div>
    </div>

    <section class="mt-8 grid gap-4 md:grid-cols-4">
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Status</p><p class="text-xl font-bold">{{ $contract->status }}</p></div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Project</p><p class="text-xl font-bold">{{ $contract->project?->name }}</p></div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Budget Ref</p><p class="text-xl font-bold">{{ $contract->budget?->id }}</p></div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">End Date</p><p class="text-xl font-bold">{{ $contract->end_date?->format('Y-m-d') ?: 'Open' }}</p></div>
    </section>

    <section class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-xl font-semibold">Contract Milestones</h2>
            <ol class="mt-4 space-y-2">
                @forelse ($contract->milestones as $milestone)
                    <li class="rounded border px-3 py-2 text-sm dark:border-slate-800">
                        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                            <span>{{ $milestone->title }} - {{ $milestone->status }} - {{ $milestone->completion_percentage ?? 0 }}%</span>
                            @if ($milestone->status !== 'completed')
                                <form method="POST" action="{{ route('admin.procurement.milestones.complete', $milestone) }}" class="flex flex-wrap gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <input name="completion_percentage" type="number" min="0" max="100" value="100" class="w-24 rounded border px-2 py-1 text-slate-950">
                                    <input name="evidence_summary" placeholder="Evidence" class="rounded border px-2 py-1 text-slate-950">
                                    <button class="rounded border px-3 py-1 text-xs font-semibold dark:border-slate-700">Accept</button>
                                </form>
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="text-sm text-slate-500">No milestones recorded.</li>
                @endforelse
            </ol>
        </div>
        <div class="rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-xl font-semibold">Payments</h2>
            <form method="POST" action="{{ route('admin.procurement.contracts.payments.store', $contract) }}" class="mt-4 grid gap-2">
                @csrf
                <input name="payment_reference" placeholder="Payment reference" class="rounded border px-3 py-2 text-slate-950">
                <input name="amount" type="number" min="0" step="0.01" placeholder="Amount" class="rounded border px-3 py-2 text-slate-950">
                <input name="paid_at" type="date" class="rounded border px-3 py-2 text-slate-950">
                <input name="status" value="certified" class="rounded border px-3 py-2 text-slate-950">
                <textarea name="notes" placeholder="Notes" class="rounded border px-3 py-2 text-slate-950"></textarea>
                <button type="submit" class="rounded bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-950">Record payment</button>
            </form>
            <ol class="mt-4 space-y-2">
                @forelse ($contract->payments as $payment)
                    <li class="rounded border px-3 py-2 text-sm dark:border-slate-800">{{ $payment->payment_reference }} - {{ number_format((float) $payment->amount, 2) }} - {{ $payment->status }}</li>
                @empty
                    <li class="text-sm text-slate-500">No payments recorded.</li>
                @endforelse
            </ol>
        </div>
        <div class="rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-xl font-semibold">Variation Orders</h2>
            <ol class="mt-4 space-y-2">
                @forelse ($contract->variationOrders as $variation)
                    <li class="rounded border px-3 py-2 text-sm dark:border-slate-800">
                        <div class="space-y-2">
                            <p>{{ $variation->title }} - {{ $variation->status }}</p>
                            @if ($variation->status !== 'approved')
                                <form method="POST" action="{{ route('admin.procurement.variation-orders.approve', $variation) }}" class="grid gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <input name="approved_amount" type="number" min="0" step="0.01" placeholder="Approved amount" class="rounded border px-3 py-2 text-slate-950">
                                    <input name="schedule_extension_days" type="number" min="0" placeholder="Extension days" class="rounded border px-3 py-2 text-slate-950">
                                    <textarea name="reason" placeholder="Reason" class="rounded border px-3 py-2 text-slate-950"></textarea>
                                    <button class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700">Approve variation</button>
                                </form>
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="text-sm text-slate-500">No variation orders recorded.</li>
                @endforelse
            </ol>
        </div>
        <div class="rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-xl font-semibold">Procurement Timeline</h2>
            <ol class="mt-4 space-y-2">
                @forelse ($contract->activities as $activity)
                    <li class="rounded border px-3 py-2 text-sm dark:border-slate-800">{{ $activity->event }} - {{ $activity->description }}</li>
                @empty
                    <li class="text-sm text-slate-500">No contract activity recorded.</li>
                @endforelse
            </ol>
        </div>
    </section>
</x-layouts.app>
