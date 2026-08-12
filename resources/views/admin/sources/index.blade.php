<x-layouts.app title="Source Registry - CivicLens">
    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Governed acquisition</p>
            <h1 class="text-3xl font-bold">Approved source registry</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-600 dark:text-slate-300">Only explicitly approved publishers and HTTPS hosts can enter the private acquisition queue. Snapshots remain private until the evidence-review workflow approves a public projection.</p>
        </div>
        <div class="flex items-center gap-3">
            <a class="cl-chip" href="{{ route('admin.sources.findings') }}">View findings</a>
            <span class="cl-chip">{{ $publishers->count() }} publishers · {{ $endpoints->total() }} endpoints</span>
        </div>
    </div>

    @if ($errors->any())
        <div class="cl-alert mt-6" role="alert">
            <div>
                <p class="font-bold">The source was not saved.</p>
                <ul class="mt-2 list-disc pl-5 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <section class="mt-8 grid gap-5 xl:grid-cols-2" aria-label="Source registry forms">
        <form method="POST" action="{{ route('admin.sources.publishers.store') }}" class="cl-card p-5">
            @csrf
            <h2 class="text-lg font-black">1. Approve a publisher</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <label class="text-sm font-semibold">Publisher name<input class="mt-1 w-full rounded border px-3 py-2 text-slate-950" name="name" value="{{ old('name') }}" required></label>
                <label class="text-sm font-semibold">Stable slug<input class="mt-1 w-full rounded border px-3 py-2 text-slate-950" name="slug" value="{{ old('slug') }}" required></label>
                <label class="text-sm font-semibold">Source class
                    <select class="mt-1 w-full rounded border px-3 py-2 text-slate-950" name="source_class" required>
                        <option value="government">Government</option>
                        <option value="non-government" @selected(old('source_class') === 'non-government')>Non-government</option>
                    </select>
                </label>
                <label class="text-sm font-semibold">Attribution name<input class="mt-1 w-full rounded border px-3 py-2 text-slate-950" name="attribution_name" value="{{ old('attribution_name') }}" required></label>
                <label class="text-sm font-semibold sm:col-span-2">Canonical HTTPS URL<input class="mt-1 w-full rounded border px-3 py-2 text-slate-950" type="url" name="canonical_url" value="{{ old('canonical_url') }}" placeholder="https://publisher.example" required></label>
                <label class="text-sm font-semibold sm:col-span-2">Rights decision<input class="mt-1 w-full rounded border px-3 py-2 text-slate-950" name="rights_decision" value="{{ old('rights_decision', 'reviewed-public-interest') }}" required></label>
            </div>
            <button class="cl-button-primary mt-4" type="submit">Approve publisher</button>
        </form>

        <form method="POST" action="{{ route('admin.sources.endpoints.store') }}" class="cl-card p-5">
            @csrf
            <h2 class="text-lg font-black">2. Add an allowlisted endpoint</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <label class="text-sm font-semibold">Publisher
                    <select class="mt-1 w-full rounded border px-3 py-2 text-slate-950" name="source_publisher_id" required>
                        <option value="">Select publisher</option>
                        @foreach ($publishers as $publisher)
                            <option value="{{ $publisher->id }}" @selected((string) old('source_publisher_id') === (string) $publisher->id)>{{ $publisher->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-semibold">Connector
                    <select class="mt-1 w-full rounded border px-3 py-2 text-slate-950" name="connector_type" required>
                        @foreach (['api' => 'API', 'feed' => 'Feed', 'sitemap' => 'Sitemap', 'direct_download' => 'Direct download', 'static_html' => 'Static HTML', 'browser' => 'Isolated browser'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('connector_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-semibold">Endpoint name<input class="mt-1 w-full rounded border px-3 py-2 text-slate-950" name="name" value="{{ old('name') }}" required></label>
                <label class="text-sm font-semibold">Allowed host<input class="mt-1 w-full rounded border px-3 py-2 text-slate-950" name="allowed_hosts[]" value="{{ old('allowed_hosts.0') }}" placeholder="publisher.example" required></label>
                <label class="text-sm font-semibold sm:col-span-2">Base HTTPS URL<input class="mt-1 w-full rounded border px-3 py-2 text-slate-950" type="url" name="base_url" value="{{ old('base_url') }}" required></label>
                <label class="text-sm font-semibold">Allowed path prefix<input class="mt-1 w-full rounded border px-3 py-2 text-slate-950" name="allowed_path_prefixes[]" value="{{ old('allowed_path_prefixes.0', '/') }}" placeholder="/publications" required></label>
                <label class="text-sm font-semibold">Access decision<input class="mt-1 w-full rounded border px-3 py-2 text-slate-950" name="access_decision" value="{{ old('access_decision', 'robots-and-terms-reviewed') }}" required></label>
                <label class="text-sm font-semibold sm:col-span-2">Access reviewed on<input class="mt-1 w-full rounded border px-3 py-2 text-slate-950" type="date" name="access_reviewed_at" value="{{ old('access_reviewed_at', now()->toDateString()) }}" required></label>
                <label class="text-sm font-semibold">Interval (minutes)<input class="mt-1 w-full rounded border px-3 py-2 text-slate-950" type="number" name="crawl_interval_minutes" value="{{ old('crawl_interval_minutes', 1440) }}" min="5" max="10080" required></label>
                <label class="text-sm font-semibold">Requests per minute<input class="mt-1 w-full rounded border px-3 py-2 text-slate-950" type="number" name="rate_limit_per_minute" value="{{ old('rate_limit_per_minute', 10) }}" min="1" max="120" required></label>
                <label class="text-sm font-semibold">Timeout (seconds)<input class="mt-1 w-full rounded border px-3 py-2 text-slate-950" type="number" name="timeout_seconds" value="{{ old('timeout_seconds', 20) }}" min="2" max="60" required></label>
                <label class="text-sm font-semibold">Maximum bytes<input class="mt-1 w-full rounded border px-3 py-2 text-slate-950" type="number" name="max_content_bytes" value="{{ old('max_content_bytes', 20971520) }}" min="1024" required></label>
            </div>
            <button class="cl-button-primary mt-4" type="submit">Add protected endpoint</button>
        </form>
    </section>

    <section class="mt-8" aria-labelledby="registered-endpoints-heading">
        <div class="mb-3 flex items-center justify-between">
            <h2 id="registered-endpoints-heading" class="text-xl font-black">Registered endpoints</h2>
            <span class="text-xs text-slate-500">Browser connectors stay fail-closed until an isolated worker is configured.</span>
        </div>
        <div class="overflow-x-auto rounded-xl border bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-100 dark:bg-slate-800"><tr><th class="px-4 py-3">Source</th><th class="px-4 py-3">Connector</th><th class="px-4 py-3">Health</th><th class="px-4 py-3">Ledger</th><th class="px-4 py-3">Controls</th></tr></thead>
                <tbody>
                    @forelse ($endpoints as $endpoint)
                        <tr class="border-t dark:border-slate-800">
                            <td class="px-4 py-3"><strong>{{ $endpoint->name }}</strong><div class="mt-1 max-w-md truncate text-xs text-slate-500">{{ $endpoint->base_url }}</div><div class="text-xs text-slate-500">{{ $endpoint->publisher->name }} · {{ str($endpoint->publisher->source_class)->headline() }}</div></td>
                            <td class="px-4 py-3">{{ str($endpoint->connector_type)->headline() }}<div class="text-xs text-slate-500">{{ $endpoint->rate_limit_per_minute }}/minute</div></td>
                            <td class="px-4 py-3"><span class="cl-chip">{{ $endpoint->paused_at ? 'Paused' : str($endpoint->health_status)->headline() }}</span>@if ($endpoint->last_error)<div class="mt-1 max-w-xs text-xs text-rose-700 dark:text-rose-300">{{ Str::limit($endpoint->last_error, 120) }}</div>@endif</td>
                            <td class="px-4 py-3">{{ $endpoint->crawl_runs_count }} runs<div class="text-xs text-slate-500">{{ $endpoint->discovered_resources_count }} resources</div></td>
                            <td class="px-4 py-3"><div class="flex flex-wrap gap-2">
                                @if ($endpoint->paused_at)
                                    <form method="POST" action="{{ route('admin.sources.endpoints.resume', $endpoint) }}">@csrf @method('PATCH')<button class="cl-button" type="submit">Resume</button></form>
                                @else
                                    <form method="POST" action="{{ route('admin.sources.endpoints.run', $endpoint) }}">@csrf<button class="cl-button-primary" type="submit">Queue now</button></form>
                                    <form method="POST" action="{{ route('admin.sources.endpoints.pause', $endpoint) }}">@csrf @method('PATCH')<button class="cl-button" type="submit">Pause</button></form>
                                @endif
                            </div></td>
                        </tr>
                    @empty
                        <tr><td class="px-4 py-8 text-slate-500" colspan="5">No publisher endpoints are approved yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $endpoints->links() }}</div>
    </section>
</x-layouts.app>
