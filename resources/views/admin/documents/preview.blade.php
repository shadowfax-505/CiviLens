<x-layouts.app :title="$document->title.' Preview - CivicLens'">
    <div>
        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Document Preview</p>
        <h1 class="text-3xl font-bold">{{ $document->title }}</h1>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Preview generation is queued for future thumbnail/OCR workers. Download remains available for authorized users.</p>
        <a href="{{ route('admin.documents.download', $document) }}" class="mt-6 inline-block rounded bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-950">Download document</a>
    </div>
</x-layouts.app>
