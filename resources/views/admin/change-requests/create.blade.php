<x-layouts.app title="New Change Request">
    <section class="mx-auto max-w-4xl space-y-8">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-emerald-600">Staff workflow</p>
            <h1 class="mt-2 text-3xl font-black">New Change Request</h1>
        </div>

        @if ($errors->any())
            <div class="rounded-2xl border border-red-300 bg-red-50 p-4 text-sm font-semibold text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-300">
                Please fix the following before submitting:
                <ul class="mt-2 list-inside list-disc font-normal">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form class="space-y-6 rounded-3xl border border-slate-200 bg-white p-8 shadow-sm dark:border-slate-800 dark:bg-slate-900" method="POST" enctype="multipart/form-data" action="{{ route('admin.change-requests.store') }}">
            @csrf
            <div class="grid gap-4 md:grid-cols-2">
                <label class="block">
                    <span class="text-sm font-semibold">Module</span>
                    <select name="module" class="mt-1 w-full rounded-2xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-950">
                        @foreach ($modules as $key => $label)
                            <option value="{{ $key }}" @selected(old('module', $defaults['module'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('module') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="text-sm font-semibold">Requested operation</span>
                    <select name="operation" class="mt-1 w-full rounded-2xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-950">
                        @foreach ($operations as $operation)
                            <option value="{{ $operation }}" @selected(old('operation', $defaults['operation'] ?? 'update') === $operation)>{{ str($operation)->headline() }}</option>
                        @endforeach
                    </select>
                    @error('operation') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="text-sm font-semibold">Subject Type</span>
                    <input name="subject_type" value="{{ old('subject_type', $defaults['subject_type'] ?? '') }}" class="mt-1 w-full rounded-2xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-950" placeholder="App\\Models\\Project">
                    @error('subject_type') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="text-sm font-semibold">Subject ID</span>
                    <input name="subject_id" value="{{ old('subject_id', $defaults['subject_id'] ?? '') }}" class="mt-1 w-full rounded-2xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-950" type="number" min="1">
                    @error('subject_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="text-sm font-semibold">Target ID</span>
                    <input name="target_id" value="{{ old('target_id', $defaults['target_id'] ?? '') }}" class="mt-1 w-full rounded-2xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-950" type="number" min="1" placeholder="Defaults to subject ID">
                    @error('target_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="text-sm font-semibold">Subject Label <span class="text-red-600">*</span></span>
                    <input name="subject_label" value="{{ old('subject_label', $defaults['subject_label'] ?? '') }}" class="mt-1 w-full rounded-2xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-950" placeholder="Project name or document title" required>
                    @error('subject_label') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="block md:col-span-2">
                    <span class="text-sm font-semibold">Subject URL</span>
                    <input name="subject_url" value="{{ old('subject_url', $defaults['subject_url'] ?? '') }}" class="mt-1 w-full rounded-2xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-950" placeholder="https://...">
                    @error('subject_url') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="text-sm font-semibold">Target Field</span>
                    <input name="target_field" value="{{ old('target_field', $defaults['target_field'] ?? '') }}" class="mt-1 w-full rounded-2xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-950" placeholder="status, title, owner">
                    @error('target_field') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="text-sm font-semibold">Summary <span class="text-red-600">*</span></span>
                    <input name="summary" value="{{ old('summary', $defaults['summary'] ?? '') }}" class="mt-1 w-full rounded-2xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-950" placeholder="What should change?" required>
                    @error('summary') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="block md:col-span-2">
                    <span class="text-sm font-semibold">Current Value</span>
                    <textarea name="current_value" class="mt-1 w-full rounded-2xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-950" rows="3">{{ old('current_value', $defaults['current_value'] ?? '') }}</textarea>
                </label>
                <label class="block md:col-span-2">
                    <span class="text-sm font-semibold">Proposed Value</span>
                    <textarea name="proposed_value" class="mt-1 w-full rounded-2xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-950" rows="3">{{ old('proposed_value', $defaults['proposed_value'] ?? '') }}</textarea>
                </label>
                <label class="block md:col-span-2">
                    <span class="text-sm font-semibold">Details</span>
                    <textarea name="details" class="mt-1 w-full rounded-2xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-950" rows="4">{{ old('details', $defaults['details'] ?? '') }}</textarea>
                </label>
                <label class="block md:col-span-2">
                    <span class="text-sm font-semibold">Structured payload (JSON)</span>
                    <textarea name="payload" class="mt-1 w-full rounded-2xl border border-slate-300 px-4 py-3 font-mono dark:border-slate-700 dark:bg-slate-950" rows="4" placeholder='{"field":"value"}'>{{ old('payload', $defaults['payload'] ?? '') }}</textarea>
                </label>
                <label class="block md:col-span-2">
                    <span class="text-sm font-semibold">Supporting attachment</span>
                    <input name="attachment" type="file" class="mt-1 block w-full text-sm">
                    <span class="mt-1 block text-xs text-slate-500">Optional; maximum 10 MB.</span>
                </label>
            </div>

            <button type="submit" class="rounded-2xl bg-emerald-600 px-5 py-3 font-bold text-white hover:bg-emerald-500">Submit Request</button>
        </form>
    </section>
</x-layouts.app>
