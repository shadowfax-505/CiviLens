<x-layouts.app :title="'Tender Detail - CivicLens'">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Tender Detail</p>
            <h1 class="text-3xl font-bold">{{ $tender->title }}</h1>
            <p class="mt-2 text-slate-600 dark:text-slate-300">{{ $tender->tender_number }} | {{ $tender->project?->name }} | {{ $tender->agency?->name }}</p>
            @if (auth()->user()?->hasRole(config('civiclens.roles.staff')))
                <a class="mt-4 inline-flex rounded-full border border-emerald-300 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-300 dark:hover:bg-emerald-950" href="{{ route('admin.change-requests.create', ['module' => 'procurement', 'subject_type' => App\Models\Tender::class, 'subject_id' => $tender->id, 'subject_label' => $tender->title, 'subject_url' => route('admin.procurement.tenders.show', $tender)]) }}">Request change</a>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            @can('update', $tender)
                <a href="{{ route('admin.procurement.tenders.edit', $tender) }}" class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700">Edit</a>
                <form method="POST" action="{{ route('admin.procurement.tenders.publish', $tender) }}">@csrf @method('PATCH')<button class="rounded bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Publish</button></form>
                <form method="POST" action="{{ route('admin.procurement.tenders.close', $tender) }}">@csrf @method('PATCH')<button class="rounded bg-slate-700 px-4 py-2 text-sm font-semibold text-white">Close</button></form>
                @if ($tender->archived_at)
                    <form method="POST" action="{{ route('admin.procurement.tenders.restore', $tender) }}">@csrf @method('PATCH')<button class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Restore</button></form>
                @else
                    <form method="POST" action="{{ route('admin.procurement.tenders.archive', $tender) }}" onsubmit="return confirm('Archive this tender?')">@csrf @method('PATCH')<button class="rounded bg-amber-700 px-4 py-2 text-sm font-semibold text-white">Archive</button></form>
                @endif
            @endcan
        </div>
    </div>

    <section class="mt-8 grid gap-4 md:grid-cols-4">
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Status</p><p class="text-xl font-bold">{{ $tender->status?->name }}</p></div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Method</p><p class="text-xl font-bold">{{ $tender->method?->name }}</p></div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Budget Reference</p><p class="text-xl font-bold">{{ number_format((float) $tender->budget?->current_allocation, 2) }}</p></div>
        <div class="rounded-xl border bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">Bids</p><p class="text-xl font-bold">{{ $tender->bidSubmissions->count() }}</p></div>
    </section>

    <section class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-xl font-semibold">Bid Submissions</h2>
            <ol class="mt-4 space-y-2">
                @forelse ($tender->bidSubmissions as $bid)
                    <li class="rounded border px-3 py-2 text-sm dark:border-slate-800">
                        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                            <span>{{ $bid->reference_number }} - {{ $bid->bidderOrganization?->name }} - {{ $bid->status }} @if ($bid->opened_at) - {{ number_format((float) $bid->bid_amount, 2) }} @endif</span>
                            @can('update', $tender)
                                @unless ($bid->opened_at)
                                    <form method="POST" action="{{ route('admin.procurement.bid-submissions.open', $bid) }}">@csrf<button class="rounded border px-3 py-1 text-xs font-semibold dark:border-slate-700">Open bid</button></form>
                                @endunless
                            @endcan
                        </div>
                    </li>
                @empty
                    <li class="text-sm text-slate-500">No bids recorded.</li>
                @endforelse
            </ol>
            @can('update', $tender)
                <form method="POST" action="{{ route('admin.procurement.tenders.bids.store', $tender) }}" class="mt-5 grid gap-3 rounded border p-3 dark:border-slate-800">
                    <p class="text-sm font-semibold">Record a new bid</p>
                    @csrf
                    <select name="bidder_organization_id" class="rounded border px-3 py-2 text-slate-950">@foreach ($bidders as $bidder)<option value="{{ $bidder->id }}">{{ $bidder->name }}</option>@endforeach</select>
                    <input name="reference_number" placeholder="Bid reference" class="rounded border px-3 py-2 text-slate-950">
                    <input name="submitted_at" placeholder="Submitted at" class="rounded border px-3 py-2 text-slate-950">
                    <input name="bid_amount" type="number" min="0" step="0.01" placeholder="Bid amount" class="rounded border px-3 py-2 text-slate-950">
                    <input name="bid_security_amount" type="number" min="0" step="0.01" placeholder="Security amount" class="rounded border px-3 py-2 text-slate-950">
                    <input name="bid_valid_until" type="date" class="rounded border px-3 py-2 text-slate-950">
                    <input name="technical_score" type="number" min="0" max="100" step="0.01" placeholder="Technical score" class="rounded border px-3 py-2 text-slate-950">
                    <input name="financial_score" type="number" min="0" max="100" step="0.01" placeholder="Financial score" class="rounded border px-3 py-2 text-slate-950">
                    <textarea name="technical_proposal_summary" placeholder="Technical proposal summary" class="rounded border px-3 py-2 text-slate-950"></textarea>
                    <textarea name="financial_proposal_summary" placeholder="Financial proposal summary" class="rounded border px-3 py-2 text-slate-950"></textarea>
                    <input name="status" value="submitted" class="rounded border px-3 py-2 text-slate-950">
                    <textarea name="notes" placeholder="Notes" class="rounded border px-3 py-2 text-slate-950"></textarea>
                    <button type="submit" class="rounded bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-950">Record bid</button>
                </form>
            @endcan
        </div>

        <div class="rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-xl font-semibold">Evaluation Workspace</h2>
            @forelse ($tender->bidSubmissions as $bid)
                @if ($bid->evaluationSummary)
                    <p class="mt-4 rounded border px-3 py-2 text-sm dark:border-slate-800">{{ $bid->reference_number }} final score: {{ $bid->evaluationSummary->overall_score }} - {{ $bid->evaluationSummary->recommendation }}</p>
                @elseif (auth()->user()?->can('update', $tender))
                    <form method="POST" action="{{ route('admin.procurement.bid-submissions.scores.store', $bid) }}" class="mt-4 grid gap-2 rounded border p-3 dark:border-slate-800">
                        @csrf
                        <p class="text-sm font-semibold">Score {{ $bid->reference_number }}</p>
                        <select name="evaluation_criterion_id" class="rounded border px-3 py-2 text-slate-950">@foreach ($tender->evaluationCriteria as $criterion)<option value="{{ $criterion->id }}">{{ $criterion->name }}</option>@endforeach</select>
                        <input name="score" type="number" min="0" step="0.01" placeholder="Score" class="rounded border px-3 py-2 text-slate-950">
                        <textarea name="comments" placeholder="Comments" class="rounded border px-3 py-2 text-slate-950"></textarea>
                        <button class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700">Record score</button>
                    </form>
                    <form method="POST" action="{{ route('admin.procurement.bid-submissions.evaluations.finalize', $bid) }}" class="mt-2 grid gap-2 rounded border p-3 dark:border-slate-800">
                        @csrf
                        <p class="text-sm font-semibold">Finalize {{ $bid->reference_number }}</p>
                        <textarea name="recommendation" placeholder="Evaluation recommendation" class="rounded border px-3 py-2 text-slate-950"></textarea>
                        <button class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Finalize evaluation</button>
                    </form>
                @endif
            @empty
                <p class="mt-4 text-sm text-slate-500">No bids to evaluate yet.</p>
            @endforelse
            @can('update', $tender)
                <form method="POST" action="{{ route('admin.procurement.tenders.criteria.store', $tender) }}" class="mt-5 grid gap-3 rounded border p-3 dark:border-slate-800">
                    <p class="text-sm font-semibold">Add evaluation criterion</p>
                    @csrf
                    <input name="name" placeholder="Criterion name" class="rounded border px-3 py-2 text-slate-950">
                    <input name="max_score" type="number" min="1" step="0.01" value="100" class="rounded border px-3 py-2 text-slate-950">
                    <input name="weight" type="number" min="0" max="100" step="0.01" placeholder="Weight" class="rounded border px-3 py-2 text-slate-950">
                    <textarea name="description" placeholder="Description" class="rounded border px-3 py-2 text-slate-950"></textarea>
                    <button type="submit" class="rounded bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-950">Add criterion</button>
                </form>
            @endcan
        </div>

        <div class="rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-xl font-semibold">Awards</h2>
            <ol class="mt-4 space-y-2">
                @forelse ($tender->awards as $award)
                    <li class="rounded border px-3 py-2 text-sm dark:border-slate-800">
                        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                            <span>{{ $award->bidSubmission?->bidderOrganization?->name }} - {{ $award->status }}</span>
                            @can('update', $tender)
                                @if ($award->status !== 'approved')
                                    <form method="POST" action="{{ route('admin.procurement.awards.approve', $award) }}">@csrf @method('PATCH')<button class="rounded bg-emerald-700 px-3 py-1 text-xs font-semibold text-white">Approve award</button></form>
                                @endif
                            @endcan
                        </div>
                    </li>
                @empty
                    <li class="text-sm text-slate-500">No awards recorded.</li>
                @endforelse
            </ol>
            @can('update', $tender)
                <form method="POST" action="{{ route('admin.procurement.tenders.awards.store', $tender) }}" class="mt-5 grid gap-3 rounded border p-3 dark:border-slate-800">
                    <p class="text-sm font-semibold">Record a new award</p>
                    @csrf
                    <select name="bid_submission_id" class="rounded border px-3 py-2 text-slate-950">@foreach ($tender->bidSubmissions as $bid)<option value="{{ $bid->id }}">{{ $bid->reference_number }} - {{ $bid->bidderOrganization?->name }}</option>@endforeach</select>
                    <input name="awarded_at" type="date" class="rounded border px-3 py-2 text-slate-950">
                    <input name="status" value="pending" class="rounded border px-3 py-2 text-slate-950">
                    <textarea name="notes" placeholder="Notes" class="rounded border px-3 py-2 text-slate-950"></textarea>
                    <button class="rounded bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-950">Record award</button>
                </form>
            @endcan
        </div>

        <div class="rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-xl font-semibold">Contracts</h2>
            @forelse ($tender->awards as $award)
                @if ($award->contract)
                    <a class="mt-4 block rounded border px-3 py-2 text-sm font-semibold text-blue-700 dark:border-slate-800 dark:text-blue-300" href="{{ route('admin.procurement.contracts.show', $award->contract) }}">{{ $award->contract->contract_number }} - {{ $award->bidSubmission?->bidderOrganization?->name }}</a>
                @elseif (auth()->user()?->can('update', $tender))
                    <form method="POST" action="{{ route('admin.procurement.awards.contracts.store', $award) }}" class="mt-4 grid gap-3 rounded border p-3 dark:border-slate-800">
                        @csrf
                        <p class="text-sm font-semibold">Contract for {{ $award->bidSubmission?->bidderOrganization?->name }}</p>
                        <input name="contract_number" placeholder="Contract number" class="rounded border px-3 py-2 text-slate-950">
                        <input name="title" placeholder="Title" class="rounded border px-3 py-2 text-slate-950">
                        <input name="status" value="draft" class="rounded border px-3 py-2 text-slate-950">
                        <input name="signed_at" type="date" class="rounded border px-3 py-2 text-slate-950">
                        <input name="start_date" type="date" class="rounded border px-3 py-2 text-slate-950">
                        <input name="end_date" type="date" class="rounded border px-3 py-2 text-slate-950">
                        <textarea name="notes" placeholder="Notes" class="rounded border px-3 py-2 text-slate-950"></textarea>
                        <button class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700">Create contract</button>
                    </form>
                @endif
            @empty
                <p class="mt-4 text-sm text-slate-500">No awards recorded yet.</p>
            @endforelse
        </div>
    </section>

    <section class="mt-8 rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-xl font-semibold">Procurement Timeline</h2>
        <ol class="mt-5 space-y-3">
            @forelse ($tender->activities as $activity)
                <li class="rounded border px-3 py-2 text-sm dark:border-slate-800"><span class="font-semibold">{{ $activity->event }}</span> - {{ $activity->description }} <span class="text-slate-500">{{ $activity->created_at?->format('Y-m-d H:i') }}</span></li>
            @empty
                <li class="text-sm text-slate-500">No procurement activity recorded.</li>
            @endforelse
        </ol>
    </section>
</x-layouts.app>
