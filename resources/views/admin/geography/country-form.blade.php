<x-layouts.app title="Country - CivicLens">
    <h1 class="text-3xl font-bold">{{ $country->exists ? 'Edit Country' : 'Create Country' }}</h1>
    <form method="POST" action="{{ $country->exists ? route('admin.geography.countries.update', $country) : route('admin.geography.countries.store') }}" class="mt-6 grid gap-4 md:grid-cols-2">
        @csrf
        @if ($country->exists)
            @method('PUT')
        @endif
        <input name="name" value="{{ old('name', $country->name) }}" placeholder="Country name" class="rounded border px-3 py-2 text-slate-950">
        <input name="iso2" value="{{ old('iso2', $country->iso2) }}" placeholder="ISO2" class="rounded border px-3 py-2 text-slate-950">
        <input name="iso3" value="{{ old('iso3', $country->iso3) }}" placeholder="ISO3" class="rounded border px-3 py-2 text-slate-950">
        <input name="phone_code" value="{{ old('phone_code', $country->phone_code) }}" placeholder="Phone code" class="rounded border px-3 py-2 text-slate-950">
        <input name="latitude" value="{{ old('latitude', $country->latitude) }}" placeholder="Latitude" class="rounded border px-3 py-2 text-slate-950">
        <input name="longitude" value="{{ old('longitude', $country->longitude) }}" placeholder="Longitude" class="rounded border px-3 py-2 text-slate-950">
        <button class="rounded bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950 md:col-span-2">Save country</button>
    </form>
</x-layouts.app>

