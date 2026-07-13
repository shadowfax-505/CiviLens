<x-layouts.app title="Division - CivicLens">
    <h1 class="text-3xl font-bold">{{ $division->exists ? 'Edit Division' : 'Create Division' }}</h1>
    <form method="POST" action="{{ $division->exists ? route('admin.geography.divisions.update', $division) : route('admin.geography.divisions.store') }}" class="mt-6 grid gap-4 md:grid-cols-2">
        @csrf
        @if ($division->exists)
            @method('PUT')
        @endif
        <select name="country_id" class="rounded border px-3 py-2 text-slate-950">
            @foreach ($countries as $country)
                <option value="{{ $country->id }}" @selected(old('country_id', $division->country_id) == $country->id)>{{ $country->name }}</option>
            @endforeach
        </select>
        <input name="name" value="{{ old('name', $division->name) }}" placeholder="Division name" class="rounded border px-3 py-2 text-slate-950">
        <input name="code" value="{{ old('code', $division->code) }}" placeholder="Code" class="rounded border px-3 py-2 text-slate-950">
        <x-leaflet-coordinate-picker :lat="$division->latitude" :lng="$division->longitude" label="Division / state map pin" />
        <button class="rounded bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950 md:col-span-2">Save division</button>
    </form>
</x-layouts.app>
