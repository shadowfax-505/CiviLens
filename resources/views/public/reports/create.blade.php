<x-layouts.app title="Submit Citizen Report">
    <section class="max-w-3xl space-y-6">
        <h1 class="text-3xl font-bold">Submit Citizen Report</h1>
        <form method="POST" action="{{ route('public.reports.store') }}" enctype="multipart/form-data" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
            @csrf
            @if ($errors->any())
                <div class="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-100" role="alert">
                    <p class="font-semibold">Your report could not be submitted. Please correct the fields below.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            <label class="block text-sm font-medium">Category
                <select name="citizen_report_category_id" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950" required>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('citizen_report_category_id') == $category->id)>{{ $category->name }}</option>
                @endforeach
                </select>
                @error('citizen_report_category_id')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm font-medium">Title
                <input name="title" value="{{ old('title') }}" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950" required>
                @error('title')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm font-medium">Description
                <textarea name="description" rows="6" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950" required>{{ old('description') }}</textarea>
                <span class="mt-1 block text-xs cl-muted">At least 20 characters.</span>
                @error('description')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm font-medium">Location
                <input name="location_text" value="{{ old('location_text') }}" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                @error('location_text')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm font-medium">Contact Preference
                <select name="contact_preference" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                    <option value="email" @selected(old('contact_preference', 'email') === 'email')>Email</option>
                    <option value="phone" @selected(old('contact_preference') === 'phone')>Phone</option>
                    <option value="none" @selected(old('contact_preference') === 'none')>No contact</option>
                </select>
                @error('contact_preference')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <x-upload-zone name="attachment" label="Upload supporting files" hint="Optional: PDF, image, or office document up to 10 MB." accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.zip" />
            @error('attachment')<span class="block text-sm text-red-600">{{ $message }}</span>@enderror
            <button class="rounded-lg bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950">Submit</button>
        </form>
    </section>
</x-layouts.app>
