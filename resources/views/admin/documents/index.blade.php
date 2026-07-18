<x-layouts.app :title="($archived ? 'Archived Documents' : 'Document Library').' - CivicLens'">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Enterprise Document Management</p>
            <h1 class="text-3xl font-bold">{{ $archived ? 'Archived Documents' : 'Document Library' }}</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-600 dark:text-slate-300">Search, govern, version, and audit every civic record across projects, procurement, finance, contractors, agencies, and geography.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.documents.index') }}" class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700">Active</a>
            <a href="{{ route('admin.documents.archived') }}" class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700">Archived</a>
            @unless ($archived)
                @can('create', App\Models\Document::class)
                    <a href="{{ route('admin.documents.create') }}" class="rounded bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-950">Upload document</a>
                @endcan
                @if (auth()->user()?->hasRole(config('civiclens.roles.staff')) === true)
                    <a href="{{ route('admin.change-requests.create', ['module' => 'documents', 'operation' => 'create', 'subject_label' => 'New document', 'subject_url' => route('admin.documents.index')]) }}" class="rounded border border-emerald-300 px-4 py-2 text-sm font-semibold text-emerald-700 dark:border-emerald-800 dark:text-emerald-300">Propose document</a>
                @endif
            @endunless
        </div>
    </div>

    <div class="mt-8 grid gap-4 md:grid-cols-4">
        @foreach ([
            'Total Documents' => $summary['total_documents'],
            'Archived' => $summary['archived_documents'],
            'Storage Usage' => number_format($summary['storage_bytes'] / 1024, 1).' KB',
            'Pending OCR' => $summary['pending_ocr'],
            'Missing Metadata' => $summary['missing_metadata'],
            'Version Count' => $summary['version_count'],
        ] as $label => $value)
            <div class="rounded-xl border bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</div>
                <div class="mt-2 text-2xl font-bold">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <form method="GET" class="mt-8 rounded-xl border bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="grid gap-3 md:grid-cols-4">
            <label class="text-sm">Global search
                <input name="search" value="{{ request('search') }}" class="mt-1 w-full rounded border px-3 py-2 text-slate-950" placeholder="Title, filename, tag">
            </label>
            <label class="text-sm">Document type
                <select name="document_type_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                    <option value="">All types</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->id }}" @selected(request('document_type_id') == $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Category
                <select name="document_category_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(request('document_category_id') == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Visibility
                <select name="document_visibility_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                    <option value="">All visibility</option>
                    @foreach ($visibilities as $visibility)
                        <option value="{{ $visibility->id }}" @selected(request('document_visibility_id') == $visibility->id)>{{ $visibility->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">File type
                <input name="file_extension" value="{{ request('file_extension') }}" class="mt-1 w-full rounded border px-3 py-2 text-slate-950" placeholder="pdf">
            </label>
            <label class="text-sm">Project
                <select name="project_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                    <option value="">All projects</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" @selected(request('project_id') == $project->id)>{{ $project->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Sort
                <select name="sort" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                    <option value="created_at" @selected(request('sort') === 'created_at')>Created</option>
                    <option value="title" @selected(request('sort') === 'title')>Title</option>
                    <option value="original_filename" @selected(request('sort') === 'original_filename')>Filename</option>
                    <option value="file_size" @selected(request('sort') === 'file_size')>File size</option>
                    <option value="version_number" @selected(request('sort') === 'version_number')>Version</option>
                </select>
            </label>
            <label class="text-sm">Direction
                <select name="direction" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                    <option value="desc" @selected(request('direction') !== 'asc')>Descending</option>
                    <option value="asc" @selected(request('direction') === 'asc')>Ascending</option>
                </select>
            </label>
        </div>
        <div class="mt-4 flex gap-3">
            <button class="rounded bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-950">Apply filters</button>
            <a href="{{ $archived ? route('admin.documents.archived') : route('admin.documents.index') }}" class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700">Reset</a>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.documents.bulk') }}" class="mt-8">
        @csrf
        <div class="overflow-x-auto rounded border bg-white dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-100 dark:bg-slate-800">
                    <tr>
                        <th class="px-4 py-3">Select</th>
                        <th class="px-4 py-3">Document</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Visibility</th>
                        <th class="px-4 py-3">Version</th>
                        <th class="px-4 py-3">Size</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        <tr class="border-t dark:border-slate-800">
                            <td class="px-4 py-3"><input type="checkbox" name="document_ids[]" value="{{ $document->id }}"></td>
                            <td class="px-4 py-3">
                                <a class="font-semibold text-blue-700 dark:text-blue-300" href="{{ route('admin.documents.show', $document) }}">{{ $document->title }}</a>
                                <div class="text-xs text-slate-500">{{ $document->original_filename }}</div>
                            </td>
                            <td class="px-4 py-3">{{ $document->type?->name }}</td>
                            <td class="px-4 py-3">{{ $document->visibility?->name }}</td>
                            <td class="px-4 py-3">v{{ $document->version_number }}</td>
                            <td class="px-4 py-3">{{ number_format($document->file_size / 1024, 1) }} KB</td>
                        </tr>
                    @empty
                        <tr class="border-t dark:border-slate-800">
                            <td class="px-4 py-8 text-slate-500" colspan="6">No documents match the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <select name="action" class="rounded border px-3 py-2 text-slate-950">
                <option value="archive">Bulk archive</option>
                <option value="tag">Bulk tag</option>
            </select>
            <select name="tag_ids[]" class="rounded border px-3 py-2 text-slate-950">
                <option value="">No tag</option>
                @foreach ($tags as $tag)
                    <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                @endforeach
            </select>
            <button class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700">Apply bulk action</button>
        </div>
    </form>

    <div class="mt-6">{{ $documents->links() }}</div>
</x-layouts.app>
