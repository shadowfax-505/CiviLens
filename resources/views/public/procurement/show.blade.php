<x-layouts.app :title="$tender->title">
    <section class="space-y-8">
        <div>
            <p class="text-sm font-semibold uppercase text-emerald-700">{{ $tender->tender_number }}</p>
            <h1 class="mt-2 text-3xl font-bold">{{ $tender->title }}</h1>
            <p class="mt-2 text-slate-600 dark:text-slate-300">{{ $tender->description }}</p>
            @if (auth()->user()?->hasRole(config('civiclens.roles.staff')))
                <a class="mt-4 inline-flex rounded-full border border-emerald-300 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-300 dark:hover:bg-emerald-950" href="{{ route('admin.change-requests.create', ['module' => 'procurement', 'subject_type' => App\Models\Tender::class, 'subject_id' => $tender->id, 'subject_label' => $tender->title, 'subject_url' => route('public.procurement.show', $tender)]) }}">Request change</a>
            @endif
            <dl class="mt-4 grid gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <dt class="text-xs text-slate-500">Status</dt>
                    <dd class="mt-1 font-medium">{{ $tender->status?->name ?? '—' }}</dd>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <dt class="text-xs text-slate-500">Agency</dt>
                    <dd class="mt-1 font-medium">{{ $tender->agency?->name ?? '—' }}</dd>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <dt class="text-xs text-slate-500">Procurement Method</dt>
                    <dd class="mt-1 font-medium">{{ $tender->method?->name ?? '—' }}</dd>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <dt class="text-xs text-slate-500">Category</dt>
                    <dd class="mt-1 font-medium">{{ $tender->category?->name ?? '—' }}</dd>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <dt class="text-xs text-slate-500">Published</dt>
                    <dd class="mt-1 font-medium">{{ $tender->published_at?->toFormattedDateString() ?? '—' }}</dd>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <dt class="text-xs text-slate-500">Submission Deadline</dt>
                    <dd class="mt-1 font-medium">{{ $tender->closing_at?->toFormattedDateString() ?? '—' }}</dd>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <dt class="text-xs text-slate-500">Project</dt>
                    <dd class="mt-1 font-medium">{{ $tender->project?->name ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        @if ($tender->lots->isNotEmpty())
            <section class="space-y-3">
                <h2 class="text-xl font-semibold">Lots</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($tender->lots as $lot)
                        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                            <p class="font-medium">{{ $lot->title ?? $lot->name }}</p>
                            @if ($lot->description)
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ str($lot->description)->limit(120) }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($tender->awards->isNotEmpty())
            <section class="space-y-3">
                <h2 class="text-xl font-semibold">Awards</h2>
                @foreach ($tender->awards as $award)
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-950">
                        <p class="font-semibold text-emerald-950 dark:text-emerald-100">
                            {{ $award->bidSubmission?->bidderOrganization?->name ?? 'Winning bidder pending publication' }}
                        </p>
                        @if ($award->contract)
                            <p class="mt-1 text-sm text-emerald-800 dark:text-emerald-200">
                                Contract: {{ $award->contract->contract_number }} · {{ $award->contract->status }}
                            </p>
                        @endif
                        @if ($award->awarded_at)
                            <p class="mt-1 text-sm text-emerald-700 dark:text-emerald-300">Awarded {{ $award->awarded_at->toFormattedDateString() }}</p>
                        @endif
                    </div>
                @endforeach
            </section>
        @endif

        @if ($tender->documents->isNotEmpty())
            <section class="space-y-3">
                <h2 class="text-xl font-semibold">Documents</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($tender->documents as $document)
                        <a href="{{ route('public.documents.download', $document) }}" class="block rounded-lg border border-slate-200 bg-white p-4 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:hover:bg-slate-800">
                            <p class="font-medium">{{ $document->title }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $document->file_extension ?? '—' }} · {{ $document->file_size ? number_format($document->file_size / 1024, 1) . ' KB' : '—' }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </section>
</x-layouts.app>
