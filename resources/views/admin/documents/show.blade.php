<x-layouts.app :title="$document->title.' - CivicLens'">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Document Detail</p>
            <h1 class="text-3xl font-bold">{{ $document->title }}</h1>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $document->original_filename }} · v{{ $document->version_number }} · {{ $document->visibility?->name }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.documents.download', $document) }}" class="rounded bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-950">Download</a>
            <a href="{{ route('admin.documents.edit', $document) }}" class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700">Edit metadata</a>
            @if ($document->archived_at)
                <form method="POST" action="{{ route('admin.documents.restore', $document) }}">@csrf @method('PATCH')<button class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Restore</button></form>
            @else
                <form method="POST" action="{{ route('admin.documents.archive', $document) }}">@csrf @method('PATCH')<button class="rounded bg-amber-700 px-4 py-2 text-sm font-semibold text-white">Archive</button></form>
            @endif
        </div>
    </div>

    <div class="mt-8 grid gap-4 md:grid-cols-4">
        @foreach ([
            'Type' => $document->type?->name,
            'Category' => $document->category?->name ?? 'Uncategorized',
            'Status' => $document->status?->name,
            'Size' => number_format($document->file_size / 1024, 1).' KB',
            'OCR' => ucfirst($document->ocr_status),
            'Index' => ucfirst($document->index_status),
            'Preview' => ucfirst($document->preview_status),
            'Checksum' => substr($document->checksum, 0, 12),
        ] as $label => $value)
            <div class="rounded-xl border bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</div>
                <div class="mt-2 break-words text-lg font-bold">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <section class="mt-8 rounded-xl border bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-xl font-semibold">Replace File</h2>
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.documents.versions.store', $document) }}" class="mt-4 grid gap-3 md:grid-cols-3">
            @csrf
            <input name="file" type="file" class="rounded border px-3 py-2">
            <input name="reason" class="rounded border px-3 py-2 text-slate-950" placeholder="Version reason">
            <button class="rounded bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-950">Create version</button>
        </form>
    </section>

    <section class="mt-8 rounded-xl border bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-xl font-semibold">Version History</h2>
        <div class="mt-4 space-y-3">
            @foreach ($document->versions->sortByDesc('version_number') as $version)
                <div class="rounded border p-3 text-sm dark:border-slate-800">
                    <div class="font-semibold">Version {{ $version->version_number }} {{ $version->is_current ? '(current)' : '' }}</div>
                    <div class="text-slate-500">{{ $version->original_filename }} · {{ $version->created_at->format('Y-m-d H:i') }} · {{ $version->reason }}</div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="mt-8 rounded-xl border bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-xl font-semibold">Audit Timeline</h2>
        <div class="mt-4 space-y-3">
            @forelse ($document->activities as $activity)
                <div class="rounded border p-3 text-sm dark:border-slate-800">
                    <div class="font-semibold">{{ $activity->event }}</div>
                    <div class="text-slate-500">{{ $activity->description }} · {{ $activity->created_at->format('Y-m-d H:i') }}</div>
                </div>
            @empty
                <p class="text-sm text-slate-500">No document activity recorded yet.</p>
            @endforelse
        </div>
    </section>
</x-layouts.app>
