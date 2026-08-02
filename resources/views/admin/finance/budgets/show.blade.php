<x-layouts.app :title="'Budget Detail - CivicLens'">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Budget Detail</p>
            <h1 class="text-3xl font-bold">{{ $budget->project?->name }}</h1>
            <p class="mt-2 text-slate-600 dark:text-slate-300">{{ $budget->notes }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if (auth()->user()?->hasRole(config('civiclens.roles.staff')) === true)
                <a href="{{ route('admin.change-requests.create', ['module' => 'budgets', 'operation' => 'update', 'subject_type' => App\Models\Budget::class, 'subject_id' => $budget->id, 'subject_label' => $budget->project?->name ?? 'Budget #'.$budget->id, 'subject_url' => route('admin.finance.budgets.show', $budget)]) }}" class="rounded border border-emerald-300 px-4 py-2 text-sm font-semibold text-emerald-700 dark:border-emerald-800 dark:text-emerald-300">Request change</a>
            @endif
            <a href="{{ route('admin.finance.budgets.edit', $budget) }}" class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700">Edit</a>
            @if ($budget->archived_at)
                <form method="POST" action="{{ route('admin.finance.budgets.restore', $budget) }}">@csrf @method('PATCH')<button class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Restore</button></form>
            @else
                <form method="POST" action="{{ route('admin.finance.budgets.archive', $budget) }}" onsubmit="return confirm('Archive this budget?')">@csrf @method('PATCH')<button class="rounded bg-amber-700 px-4 py-2 text-sm font-semibold text-white">Archive</button></form>
            @endif
        </div>
    </div>

    <section class="mt-8 grid gap-4 md:grid-cols-4">
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Current Allocation</p><p class="text-xl font-bold">{{ number_format((float) $budget->current_allocation, 2) }}</p></div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Actual Expenditure</p><p class="text-xl font-bold">{{ number_format((float) $budget->actual_expenditure, 2) }}</p></div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Remaining Balance</p><p class="text-xl font-bold">{{ number_format($budget->remaining_balance, 2) }}</p></div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Utilization</p><p class="text-xl font-bold">{{ $budget->utilization_percentage }}%</p></div>
    </section>

    <section class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-xl font-semibold">Revision History</h2>
            <form method="POST" action="{{ route('admin.finance.budgets.revisions.store', $budget) }}" class="mt-4 grid gap-3">
                @csrf
                <input name="new_allocation" type="number" min="0" step="0.01" placeholder="New allocation" class="rounded border px-3 py-2 text-slate-950">
                <input name="approval_date" type="date" class="rounded border px-3 py-2 text-slate-950">
                <textarea name="reason" placeholder="Reason" class="rounded border px-3 py-2 text-slate-950"></textarea>
                <button type="submit" class="rounded bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-950">Record revision</button>
            </form>
            <ol class="mt-5 space-y-2">
                @forelse ($budget->revisions as $revision)
                    <li class="rounded border px-3 py-2 text-sm dark:border-slate-800">Revision {{ $revision->revision_number }}: {{ number_format((float) $revision->previous_allocation, 2) }} -> {{ number_format((float) $revision->new_allocation, 2) }} ({{ number_format((float) $revision->difference, 2) }})</li>
                @empty
                    <li class="text-sm text-slate-500">No revisions recorded.</li>
                @endforelse
            </ol>
        </div>

        <div class="rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-xl font-semibold">Transaction History</h2>
            <form method="POST" action="{{ route('admin.finance.budgets.transactions.store', $budget) }}" class="mt-4 grid gap-3">
                @csrf
                <select name="budget_transaction_type_id" class="rounded border px-3 py-2 text-slate-950">
                    @foreach (App\Models\BudgetTransactionType::query()->orderBy('name')->get() as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
                <input name="amount" type="number" min="0" step="0.01" placeholder="Amount" class="rounded border px-3 py-2 text-slate-950">
                <input name="transaction_date" type="date" class="rounded border px-3 py-2 text-slate-950">
                <textarea name="description" placeholder="Description" class="rounded border px-3 py-2 text-slate-950"></textarea>
                <button type="submit" class="rounded bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-950">Record transaction</button>
            </form>
            <ol class="mt-5 space-y-2">
                @forelse ($budget->transactions as $transaction)
                    <li class="rounded border px-3 py-2 text-sm dark:border-slate-800">{{ $transaction->transaction_date?->format('Y-m-d') }}: {{ $transaction->type?->name }} {{ number_format((float) $transaction->amount, 2) }} - {{ $transaction->description }}</li>
                @empty
                    <li class="text-sm text-slate-500">No transactions recorded.</li>
                @endforelse
            </ol>
        </div>
    </section>
</x-layouts.app>

