<x-layouts.app :title="($document->exists ? 'Edit Document' : 'Upload Document').' - CivicLens'">
    <div>
        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Document Management</p>
        <h1 class="text-3xl font-bold">{{ $document->exists ? 'Edit Metadata' : 'Upload Wizard' }}</h1>
    </div>

    <form method="POST" enctype="multipart/form-data" action="{{ $document->exists ? route('admin.documents.update', $document) : route('admin.documents.store') }}" class="mt-8 rounded-xl border bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @csrf
        @if ($document->exists)
            @method('PUT')
        @endif

        <div class="grid gap-4 md:grid-cols-2">
            <label class="text-sm">Title
                <input name="title" value="{{ old('title', $document->title) }}" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                @error('title') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
            <label class="text-sm">Language
                <input name="language" value="{{ old('language', $document->language ?? 'en') }}" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
            </label>
            <label class="text-sm">Type
                <select name="document_type_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                    @foreach ($types as $type)
                        <option value="{{ $type->id }}" @selected(old('document_type_id', $document->document_type_id) == $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Category
                <select name="document_category_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                    <option value="">Uncategorized</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('document_category_id', $document->document_category_id) == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Status
                <select name="document_status_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                    @foreach ($statuses as $status)
                        <option value="{{ $status->id }}" @selected(old('document_status_id', $document->document_status_id) == $status->id)>{{ $status->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Visibility
                <select name="document_visibility_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                    @foreach ($visibilities as $visibility)
                        <option value="{{ $visibility->id }}" @selected(old('document_visibility_id', $document->document_visibility_id) == $visibility->id)>{{ $visibility->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Project relationship
                <select name="documentable_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                    <option value="">No project</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}">{{ $project->name }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="documentable_type" value="project">
            </label>
            <label class="text-sm">Tags
                <select name="tag_ids[]" multiple class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                    @foreach ($tags as $tag)
                        <option value="{{ $tag->id }}" @selected(in_array($tag->id, old('tag_ids', $document->exists ? $document->tags->pluck('id')->all() : [])))>{{ $tag->name }}</option>
                    @endforeach
                </select>
            </label>
            @unless ($document->exists)
                <label class="text-sm md:col-span-2">File
                    <input name="file" type="file" class="mt-1 w-full rounded border px-3 py-2">
                    @error('file') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
            @endunless
            <label class="text-sm md:col-span-2">Description
                <textarea name="description" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">{{ old('description', $document->description) }}</textarea>
            </label>
        </div>

        <div class="mt-6 flex gap-3">
            <button class="rounded bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-950">{{ $document->exists ? 'Save metadata' : 'Upload document' }}</button>
            <a href="{{ route('admin.documents.index') }}" class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700">Cancel</a>
        </div>
    </form>
</x-layouts.app>
