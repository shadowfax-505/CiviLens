<x-layouts.app :title="($tender->exists ? 'Edit Tender' : 'Create Tender').' - CivicLens'">
    <h1 class="text-3xl font-bold">{{ $tender->exists ? 'Edit Tender' : 'Create Tender' }}</h1>

    @if ($errors->any())
        <div class="mt-6 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
            Please review the highlighted fields and try again.
        </div>
    @endif

    <form method="POST" action="{{ $tender->exists ? route('admin.procurement.tenders.update', $tender) : route('admin.procurement.tenders.store') }}" class="mt-6 grid gap-4 md:grid-cols-2">
        @csrf
        @if ($tender->exists)
            @method('PUT')
        @endif
        <div><select name="project_id" class="w-full rounded border px-3 py-2 text-slate-950">@foreach ($projects as $project)<option value="{{ $project->id }}" @selected(old('project_id', $tender->project_id) == $project->id)>{{ $project->name }}</option>@endforeach</select>
        @error('project_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror</div>
        <div><select name="budget_id" class="w-full rounded border px-3 py-2 text-slate-950">@foreach ($budgets as $budget)<option value="{{ $budget->id }}" @selected(old('budget_id', $tender->budget_id) == $budget->id)>{{ $budget->project?->name }} - {{ $budget->fiscalYear?->name }} - {{ number_format((float) $budget->current_allocation, 2) }}</option>@endforeach</select>
        @error('budget_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror</div>
        <div><select name="agency_id" class="w-full rounded border px-3 py-2 text-slate-950">@foreach ($agencies as $agency)<option value="{{ $agency->id }}" @selected(old('agency_id', $tender->agency_id) == $agency->id)>{{ $agency->name }}</option>@endforeach</select>
        @error('agency_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror</div>
        <div><select name="procurement_method_id" class="w-full rounded border px-3 py-2 text-slate-950">@foreach ($methods as $method)<option value="{{ $method->id }}" @selected(old('procurement_method_id', $tender->procurement_method_id) == $method->id)>{{ $method->name }}</option>@endforeach</select>
        @error('procurement_method_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror</div>
        <div><select name="tender_category_id" class="w-full rounded border px-3 py-2 text-slate-950">@foreach ($categories as $category)<option value="{{ $category->id }}" @selected(old('tender_category_id', $tender->tender_category_id) == $category->id)>{{ $category->name }}</option>@endforeach</select>
        @error('tender_category_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror</div>
        <div><select name="tender_status_id" class="w-full rounded border px-3 py-2 text-slate-950">@foreach ($statuses as $status)<option value="{{ $status->id }}" @selected(old('tender_status_id', $tender->tender_status_id) == $status->id)>{{ $status->name }}</option>@endforeach</select>
        @error('tender_status_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror</div>
        <div><input name="tender_number" value="{{ old('tender_number', $tender->tender_number) }}" placeholder="Tender number" class="w-full rounded border px-3 py-2 text-slate-950">
        @error('tender_number') <span class="text-xs text-red-600">{{ $message }}</span> @enderror</div>
        <div><input name="title" value="{{ old('title', $tender->title) }}" placeholder="Title" class="w-full rounded border px-3 py-2 text-slate-950">
        @error('title') <span class="text-xs text-red-600">{{ $message }}</span> @enderror</div>
        <div><input name="slug" value="{{ old('slug', $tender->slug) }}" placeholder="Slug" class="w-full rounded border px-3 py-2 text-slate-950">
        @error('slug') <span class="text-xs text-red-600">{{ $message }}</span> @enderror</div>
        <div><input name="published_at" value="{{ old('published_at', optional($tender->published_at)->format('Y-m-d H:i:s')) }}" placeholder="Published at" class="w-full rounded border px-3 py-2 text-slate-950">
        @error('published_at') <span class="text-xs text-red-600">{{ $message }}</span> @enderror</div>
        <div><input name="closing_at" value="{{ old('closing_at', optional($tender->closing_at)->format('Y-m-d H:i:s')) }}" placeholder="Closing at" class="w-full rounded border px-3 py-2 text-slate-950">
        @error('closing_at') <span class="text-xs text-red-600">{{ $message }}</span> @enderror</div>
        <div class="md:col-span-2"><textarea name="description" placeholder="Description" class="w-full rounded border px-3 py-2 text-slate-950">{{ old('description', $tender->description) }}</textarea>
        @error('description') <span class="text-xs text-red-600">{{ $message }}</span> @enderror</div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_public" value="1" @checked(old('is_public', $tender->exists ? $tender->is_public : true))> Public tender</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $tender->exists ? $tender->is_active : true))> Active tender</label>
        <button class="rounded bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950 md:col-span-2">Save tender</button>
    </form>
</x-layouts.app>
