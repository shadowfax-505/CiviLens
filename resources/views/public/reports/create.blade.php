<x-layouts.app title="Submit Citizen Report">
    <section class="max-w-3xl space-y-6">
        <h1 class="text-3xl font-bold">Submit Citizen Report</h1>
        <form method="POST" action="{{ route('public.reports.store') }}" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
            @csrf
            <label class="block text-sm font-medium">Category
                <select name="citizen_report_category_id" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950" required>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm font-medium">Title
                <input name="title" value="{{ old('title') }}" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950" required>
            </label>
            <label class="block text-sm font-medium">Description
                <textarea name="description" rows="6" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950" required>{{ old('description') }}</textarea>
            </label>
            <label class="block text-sm font-medium">Location
                <input name="location_text" value="{{ old('location_text') }}" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950">
            </label>
            <label class="block text-sm font-medium">Contact Preference
                <select name="contact_preference" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                    <option value="email">Email</option>
                    <option value="phone">Phone</option>
                    <option value="none">No contact</option>
                </select>
            </label>
            <button class="rounded-lg bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950">Submit</button>
        </form>
    </section>
</x-layouts.app>
