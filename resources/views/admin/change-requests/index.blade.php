<x-layouts.app title="Change Requests">
    <section class="space-y-8">
        <div class="rounded-3xl bg-white p-8 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-emerald-600">Staff workflow</p>
                    <h1 class="mt-2 text-3xl font-black">Staff Requests</h1>
                    <p class="mt-2 max-w-3xl text-slate-600 dark:text-slate-300">Track staff-submitted correction requests for projects, documents, contractors, procurement, and agencies.</p>
                </div>
                @if ($canSubmit)
                    <a class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-500" href="{{ route('admin.change-requests.create', request()->query()) }}">New Request</a>
                @endif
            </div>

            <form class="mt-6 grid gap-3 md:grid-cols-[1fr_180px_auto]" method="GET" action="{{ route('admin.change-requests.index') }}">
                <select name="module" class="rounded-2xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-950">
                    <option value="">All modules</option>
                    @foreach ($modules as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['module'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="status" class="rounded-2xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-950">
                    <option value="">All statuses</option>
                    @foreach (['pending', 'approved', 'rejected', 'dismissed'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->headline() }}</option>
                    @endforeach
                </select>
                <button class="rounded-2xl bg-slate-950 px-4 py-3 font-bold text-white dark:bg-white dark:text-slate-950">Filter</button>
            </form>
        </div>

        <div class="grid gap-4">
            @forelse ($changeRequests as $changeRequest)
                <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-600 dark:text-emerald-300">{{ $modules[$changeRequest->module] ?? str($changeRequest->module)->headline() }}</p>
                            <h2 class="mt-1 text-xl font-black"><a href="{{ route('admin.change-requests.show', $changeRequest) }}">{{ $changeRequest->summary }}</a></h2>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $changeRequest->subject_label }} @if($changeRequest->target_field) · {{ $changeRequest->target_field }} @endif</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ str($changeRequest->status)->headline() }}</span>
                    </div>
                </article>
            @empty
                <div class="rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="text-2xl font-black">No change requests yet</h2>
                    <p class="mt-2 text-slate-600 dark:text-slate-300">Staff can submit a request when a project, document, contractor, procurement record, or agency needs correction.</p>
                </div>
            @endforelse
        </div>

        {{ $changeRequests->links() }}
    </section>
</x-layouts.app>
