<x-layouts.app title="Create Procurement Plan">
    <section class="max-w-4xl space-y-6">
        <h1 class="text-3xl font-bold">Create Procurement Plan</h1>

        @if ($errors->any())
            <div class="mt-6 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                Please review the highlighted fields and try again.
            </div>
        @endif

        <form method="POST" action="{{ route('admin.procurement.plans.store') }}" class="mt-6 grid gap-4 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900 md:grid-cols-2">
            @csrf
            <label class="text-sm font-medium">Plan number
                <input name="plan_number" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950" required>
                @error('plan_number') <span class="text-xs text-red-600 block">{{ $message }}</span> @enderror
            </label>
            <label class="text-sm font-medium">Title
                <input name="title" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950" required>
                @error('title') <span class="text-xs text-red-600 block">{{ $message }}</span> @enderror
            </label>
            <label class="text-sm font-medium">Agency
                <select name="agency_id" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950">@foreach ($agencies as $agency)<option value="{{ $agency->id }}">{{ $agency->name }}</option>@endforeach</select>
                @error('agency_id') <span class="text-xs text-red-600 block">{{ $message }}</span> @enderror
            </label>
            <label class="text-sm font-medium">Budget
                <select name="budget_id" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950">@foreach ($budgets as $budget)<option value="{{ $budget->id }}">{{ $budget->budget_code ?? $budget->id }} · {{ $budget->project?->name }}</option>@endforeach</select>
                @error('budget_id') <span class="text-xs text-red-600 block">{{ $message }}</span> @enderror
            </label>
            <label class="text-sm font-medium">Project
                <select name="project_id" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950">@foreach ($projects as $project)<option value="{{ $project->id }}">{{ $project->name }}</option>@endforeach</select>
                @error('project_id') <span class="text-xs text-red-600 block">{{ $message }}</span> @enderror
            </label>
            <label class="text-sm font-medium">Fiscal year
                <select name="fiscal_year_id" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950">@foreach ($fiscalYears as $fiscalYear)<option value="{{ $fiscalYear->id }}">{{ $fiscalYear->name }}</option>@endforeach</select>
                @error('fiscal_year_id') <span class="text-xs text-red-600 block">{{ $message }}</span> @enderror
            </label>
            <label class="text-sm font-medium">Funding source
                <select name="funding_source_id" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950">@foreach ($fundingSources as $fundingSource)<option value="{{ $fundingSource->id }}">{{ $fundingSource->name }}</option>@endforeach</select>
                @error('funding_source_id') <span class="text-xs text-red-600 block">{{ $message }}</span> @enderror
            </label>
            <label class="text-sm font-medium">Method
                <select name="procurement_method_id" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950">@foreach ($methods as $method)<option value="{{ $method->id }}">{{ $method->name }}</option>@endforeach</select>
                @error('procurement_method_id') <span class="text-xs text-red-600 block">{{ $message }}</span> @enderror
            </label>
            <label class="text-sm font-medium">Estimated value
                <input name="estimated_value" type="number" step="0.01" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950" required>
                @error('estimated_value') <span class="text-xs text-red-600 block">{{ $message }}</span> @enderror
            </label>
            <label class="text-sm font-medium">Priority
                <select name="priority" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950"><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option></select>
                @error('priority') <span class="text-xs text-red-600 block">{{ $message }}</span> @enderror
            </label>
            <input type="hidden" name="status" value="draft">
            <div class="md:col-span-2">
                <button class="rounded-lg bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950">Save plan</button>
            </div>
        </form>
    </section>
</x-layouts.app>
