<x-layouts.app title="Public Documents">
    <section class="space-y-6">
        <h1 class="text-3xl font-bold">Public Documents</h1>
        <form method="GET" class="grid gap-3 md:grid-cols-4">
            <input name="q" value="{{ $filters['q'] ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950" placeholder="Search documents">
            <select name="document_type_id"><option value="">All types</option>@foreach ($types as $type)<option value="{{ $type->id }}" @selected(($filters['document_type_id'] ?? null) == $type->id)>{{ $type->name }}</option>@endforeach</select>
            <select name="document_category_id"><option value="">All categories</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected(($filters['document_category_id'] ?? null) == $category->id)>{{ $category->name }}</option>@endforeach</select>
            <button class="rounded-lg bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950">Search</button>
        </form>
        @forelse ($documents as $document)
            <article class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <a class="font-semibold" href="{{ route('public.documents.download', $document) }}">{{ $document->title }}</a>
                <p class="mt-1 text-sm text-slate-500">{{ $document->type?->name }} · {{ $document->category?->name }}</p>
            </article>
        @empty
            <p class="text-slate-500">No public documents match the current filters.</p>
        @endforelse
        {{ $documents->links() }}
    </section>
</x-layouts.app>
