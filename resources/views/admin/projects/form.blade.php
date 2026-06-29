<x-layouts.app :title="($project->exists ? 'Edit Project' : 'Create Project').' - CivicLens'">
    <h1 class="text-3xl font-bold">{{ $project->exists ? 'Edit Project' : 'Create Project' }}</h1>

    @if ($errors->any())
        <div class="mt-6 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
            Please review the highlighted fields and try again.
        </div>
    @endif

    <form method="POST" action="{{ $project->exists ? route('admin.projects.update', $project) : route('admin.projects.store') }}" class="mt-6 grid gap-4 md:grid-cols-2">
        @csrf
        @if ($project->exists)
            @method('PUT')
        @endif

        <label class="text-sm">Project code
            <input name="project_code" value="{{ old('project_code', $project->project_code) }}" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
        </label>
        <label class="text-sm">Project name
            <input name="name" value="{{ old('name', $project->name) }}" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
        </label>
        <label class="text-sm">Short name
            <input name="short_name" value="{{ old('short_name', $project->short_name) }}" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
        </label>
        <label class="text-sm">Slug
            <input name="slug" value="{{ old('slug', $project->slug) }}" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
        </label>
        <label class="text-sm">Agency
            <select name="agency_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                @foreach ($agencies as $agency)
                    <option value="{{ $agency->id }}" @selected(old('agency_id', $project->agency_id) == $agency->id)>{{ $agency->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">Parent project
            <select name="parent_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                <option value="">No parent</option>
                @foreach ($parentProjects as $parent)
                    <option value="{{ $parent->id }}" @selected(old('parent_id', $project->parent_id) == $parent->id)>{{ $parent->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">Category
            <select name="project_category_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('project_category_id', $project->project_category_id) == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">Status
            <select name="project_status_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                @foreach ($statuses as $status)
                    <option value="{{ $status->id }}" @selected(old('project_status_id', $project->project_status_id) == $status->id)>{{ $status->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">Priority
            <select name="project_priority_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                @foreach ($priorities as $priority)
                    <option value="{{ $priority->id }}" @selected(old('project_priority_id', $project->project_priority_id) == $priority->id)>{{ $priority->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">Funding source
            <select name="funding_source_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                @foreach ($fundingSources as $source)
                    <option value="{{ $source->id }}" @selected(old('funding_source_id', $project->funding_source_id) == $source->id)>{{ $source->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">Fiscal year
            <select name="fiscal_year_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                @foreach ($fiscalYears as $year)
                    <option value="{{ $year->id }}" @selected(old('fiscal_year_id', $project->fiscal_year_id) == $year->id)>{{ $year->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">Country
            <select name="country_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                <option value="">No country</option>
                @foreach ($countries as $country)
                    <option value="{{ $country->id }}" @selected(old('country_id', $project->country_id) == $country->id)>{{ $country->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">Estimated budget
            <input name="estimated_budget" value="{{ old('estimated_budget', $project->estimated_budget) }}" type="number" min="0" step="0.01" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
        </label>
        <label class="text-sm">Approved budget
            <input name="approved_budget" value="{{ old('approved_budget', $project->approved_budget) }}" type="number" min="0" step="0.01" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
        </label>
        <label class="text-sm">Spent amount
            <input name="spent_amount" value="{{ old('spent_amount', $project->spent_amount) }}" type="number" min="0" step="0.01" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
        </label>
        <label class="text-sm">Progress percentage
            <input name="progress_percentage" value="{{ old('progress_percentage', $project->progress_percentage ?? 0) }}" type="number" min="0" max="100" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
        </label>
        <label class="text-sm">Planned start
            <input name="planned_start_date" value="{{ old('planned_start_date', $project->planned_start_date?->format('Y-m-d')) }}" type="date" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
        </label>
        <label class="text-sm">Planned end
            <input name="planned_end_date" value="{{ old('planned_end_date', $project->planned_end_date?->format('Y-m-d')) }}" type="date" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
        </label>
        <label class="text-sm">Actual start
            <input name="actual_start_date" value="{{ old('actual_start_date', $project->actual_start_date?->format('Y-m-d')) }}" type="date" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
        </label>
        <label class="text-sm">Actual end
            <input name="actual_end_date" value="{{ old('actual_end_date', $project->actual_end_date?->format('Y-m-d')) }}" type="date" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
        </label>
        <label class="text-sm">Latitude
            <input name="latitude" value="{{ old('latitude', $project->latitude) }}" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
        </label>
        <label class="text-sm">Longitude
            <input name="longitude" value="{{ old('longitude', $project->longitude) }}" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
        </label>
        <label class="text-sm md:col-span-2">Featured image path
            <input name="featured_image_path" value="{{ old('featured_image_path', $project->featured_image_path) }}" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
        </label>
        <label class="text-sm md:col-span-2">Description
            <textarea name="description" class="mt-1 min-h-32 w-full rounded border px-3 py-2 text-slate-950">{{ old('description', $project->description) }}</textarea>
        </label>
        <div class="flex gap-6 md:col-span-2">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_public" value="1" @checked(old('is_public', $project->is_public))> Publicly visible</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $project->exists ? $project->is_active : true))> Active</label>
        </div>
        <button class="rounded bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950 md:col-span-2">Save project</button>
    </form>
</x-layouts.app>

