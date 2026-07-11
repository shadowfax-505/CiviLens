<x-layouts.app title="Agency - CivicLens">
    <h1 class="text-3xl font-bold">{{ $agency->exists ? 'Edit Agency' : 'Create Agency' }}</h1>

    <form method="POST" action="{{ $agency->exists ? route('admin.agencies.update', $agency) : route('admin.agencies.store') }}" class="mt-6 grid gap-4 md:grid-cols-2">
        @csrf
        @if ($agency->exists)
            @method('PUT')
        @endif

        <input name="name" value="{{ old('name', $agency->name) }}" placeholder="Agency name" class="rounded border px-3 py-2 text-slate-950">
        <input name="short_name" value="{{ old('short_name', $agency->short_name) }}" placeholder="Short name" class="rounded border px-3 py-2 text-slate-950">
        <input name="slug" value="{{ old('slug', $agency->slug) }}" placeholder="agency-slug" class="rounded border px-3 py-2 text-slate-950">
        <select name="status" class="rounded border px-3 py-2 text-slate-950">
            @foreach (App\Models\Agency::STATUSES as $status)
                <option value="{{ $status }}" @selected(old('status', $agency->status ?: 'active') === $status)>{{ str($status)->headline() }}</option>
            @endforeach
        </select>
        <select name="agency_type_id" class="rounded border px-3 py-2 text-slate-950">
            @foreach ($agencyTypes as $type)
                <option value="{{ $type->id }}" @selected(old('agency_type_id', $agency->agency_type_id) == $type->id)>{{ $type->name }}</option>
            @endforeach
        </select>
        <select name="parent_id" class="rounded border px-3 py-2 text-slate-950">
            <option value="">No parent agency</option>
            @foreach ($parentAgencies as $parent)
                <option value="{{ $parent->id }}" @selected(old('parent_id', $agency->parent_id) == $parent->id)>{{ $parent->name }}</option>
            @endforeach
        </select>
        <select name="country_id" class="rounded border px-3 py-2 text-slate-950">
            <option value="">No country assigned</option>
            @foreach ($countries as $country)
                <option value="{{ $country->id }}" @selected(old('country_id', $agency->country_id) == $country->id)>{{ $country->name }}</option>
            @endforeach
        </select>
        <input name="email" value="{{ old('email', $agency->email) }}" placeholder="Email" class="rounded border px-3 py-2 text-slate-950">
        <input name="phone" value="{{ old('phone', $agency->phone) }}" placeholder="Phone" class="rounded border px-3 py-2 text-slate-950">
        <input name="website" value="{{ old('website', $agency->website) }}" placeholder="Website" class="rounded border px-3 py-2 text-slate-950">
        <input name="contact_person" value="{{ old('contact_person', $agency->contact_person) }}" placeholder="Contact person" class="rounded border px-3 py-2 text-slate-950">
        <textarea name="address" placeholder="Address" class="rounded border px-3 py-2 text-slate-950 md:col-span-2">{{ old('address', $agency->address) }}</textarea>
        <div class="md:col-span-2"><x-leaflet-coordinate-picker :lat="old('latitude', $agency->latitude)" :lng="old('longitude', $agency->longitude)" label="Agency location" /></div>
        <textarea name="description" placeholder="Description" class="rounded border px-3 py-2 text-slate-950 md:col-span-2">{{ old('description', $agency->description) }}</textarea>

        <fieldset class="rounded border p-4 md:col-span-2 dark:border-slate-800">
            <legend class="px-2 text-sm font-semibold">Assigned users</legend>
            <div class="grid gap-2 md:grid-cols-3">
                @foreach ($users as $user)
                    <label class="flex gap-2 text-sm">
                        <input type="checkbox" name="user_ids[]" value="{{ $user->id }}" @checked(collect(old('user_ids', $agency->exists ? $agency->users->pluck('id')->all() : []))->contains($user->id))>
                        <span>{{ $user->name }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <button class="rounded bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950 md:col-span-2">Save agency</button>
    </form>
</x-layouts.app>
