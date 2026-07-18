<x-layouts.app :title="($budget->exists ? 'Edit Budget' : 'Create Budget').' - CivicLens'">
    <h1 class="text-3xl font-bold">{{ $budget->exists ? 'Edit Budget' : 'Create Budget' }}</h1>

    @if ($errors->any())
        <div class="mt-6 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
            Please review the highlighted fields and try again.
        </div>
    @endif

    <form method="POST" action="{{ $budget->exists ? route('admin.finance.budgets.update', $budget) : route('admin.finance.budgets.store') }}" class="mt-6 grid gap-4 md:grid-cols-2">
        @csrf
        @if ($budget->exists)
            @method('PUT')
        @endif
        <div><select name="project_id" class="w-full rounded border px-3 py-2 text-slate-950">@foreach ($projects as $project)<option value="{{ $project->id }}" @selected(old('project_id', $budget->project_id) == $project->id)>{{ $project->name }}</option>@endforeach</select>
        @error('project_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror</div>
        <div><select name="fiscal_year_id" class="w-full rounded border px-3 py-2 text-slate-950">@foreach ($fiscalYears as $year)<option value="{{ $year->id }}" @selected(old('fiscal_year_id', $budget->fiscal_year_id) == $year->id)>{{ $year->name }}</option>@endforeach</select>
        @error('fiscal_year_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror</div>
        <div><select name="funding_source_id" class="w-full rounded border px-3 py-2 text-slate-950">@foreach ($fundingSources as $source)<option value="{{ $source->id }}" @selected(old('funding_source_id', $budget->funding_source_id) == $source->id)>{{ $source->name }}</option>@endforeach</select>
        @error('funding_source_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror</div>
        <div><select name="budget_category_id" class="w-full rounded border px-3 py-2 text-slate-950">@foreach ($categories as $category)<option value="{{ $category->id }}" @selected(old('budget_category_id', $budget->budget_category_id) == $category->id)>{{ $category->name }}</option>@endforeach</select>
        @error('budget_category_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror</div>
        <div><select name="budget_type_id" class="w-full rounded border px-3 py-2 text-slate-950">@foreach ($types as $type)<option value="{{ $type->id }}" @selected(old('budget_type_id', $budget->budget_type_id) == $type->id)>{{ $type->name }}</option>@endforeach</select>
        @error('budget_type_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror</div>
        <div><select name="budget_status_id" class="w-full rounded border px-3 py-2 text-slate-950">@foreach ($statuses as $status)<option value="{{ $status->id }}" @selected(old('budget_status_id', $budget->budget_status_id) == $status->id)>{{ $status->name }}</option>@endforeach</select>
        @error('budget_status_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror</div>
        <div><input name="original_allocation" value="{{ old('original_allocation', $budget->original_allocation) }}" type="number" min="0" step="0.01" placeholder="Original allocation" class="w-full rounded border px-3 py-2 text-slate-950">
        @error('original_allocation') <span class="text-xs text-red-600">{{ $message }}</span> @enderror</div>
        <div><input name="current_allocation" value="{{ old('current_allocation', $budget->current_allocation) }}" type="number" min="0" step="0.01" placeholder="Current allocation" class="w-full rounded border px-3 py-2 text-slate-950">
        @error('current_allocation') <span class="text-xs text-red-600">{{ $message }}</span> @enderror</div>
        <div><input name="reserved_amount" value="{{ old('reserved_amount', $budget->reserved_amount) }}" type="number" min="0" step="0.01" placeholder="Reserved amount" class="w-full rounded border px-3 py-2 text-slate-950">
        @error('reserved_amount') <span class="text-xs text-red-600">{{ $message }}</span> @enderror</div>
        <div><input name="committed_amount" value="{{ old('committed_amount', $budget->committed_amount) }}" type="number" min="0" step="0.01" placeholder="Committed amount" class="w-full rounded border px-3 py-2 text-slate-950">
        @error('committed_amount') <span class="text-xs text-red-600">{{ $message }}</span> @enderror</div>
        <div><input name="actual_expenditure" value="{{ old('actual_expenditure', $budget->actual_expenditure) }}" type="number" min="0" step="0.01" placeholder="Actual expenditure" class="w-full rounded border px-3 py-2 text-slate-950">
        @error('actual_expenditure') <span class="text-xs text-red-600">{{ $message }}</span> @enderror</div>
        <div><input name="currency" value="{{ old('currency', $budget->currency ?: 'BDT') }}" maxlength="3" placeholder="Currency" class="w-full rounded border px-3 py-2 text-slate-950">
        @error('currency') <span class="text-xs text-red-600">{{ $message }}</span> @enderror</div>
        <div class="md:col-span-2"><textarea name="notes" placeholder="Notes" class="w-full rounded border px-3 py-2 text-slate-950">{{ old('notes', $budget->notes) }}</textarea>
        @error('notes') <span class="text-xs text-red-600">{{ $message }}</span> @enderror</div>
        <label class="flex items-center gap-2 text-sm md:col-span-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $budget->exists ? $budget->is_active : true))> Active budget</label>
        <button class="rounded bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950 md:col-span-2">Save budget</button>
    </form>
</x-layouts.app>

