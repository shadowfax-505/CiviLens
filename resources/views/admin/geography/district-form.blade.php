<x-layouts.app title="District - CivicLens">
    <h1 class="text-3xl font-bold">{{ $district->exists ? 'Edit District' : 'Create District' }}</h1>
    <form method="POST" action="{{ $district->exists ? route('admin.geography.districts.update', $district) : route('admin.geography.districts.store') }}" class="mt-6 grid gap-4 md:grid-cols-2">
        @csrf
        @if ($district->exists)
            @method('PUT')
        @endif
        <select name="division_id" class="rounded border px-3 py-2 text-slate-950">
            @foreach ($divisions as $division)
                <option value="{{ $division->id }}" @selected(old('division_id', $district->division_id) == $division->id)>{{ $division->name }}</option>
            @endforeach
        </select>
        <input name="name" value="{{ old('name', $district->name) }}" placeholder="District name" class="rounded border px-3 py-2 text-slate-950">
        <input name="code" value="{{ old('code', $district->code) }}" placeholder="Code" class="rounded border px-3 py-2 text-slate-950">
        <x-leaflet-coordinate-picker :lat="$district->latitude" :lng="$district->longitude" label="District map pin" />
        <button type="submit" class="rounded bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950 md:col-span-2">Save district</button>
    </form>
</x-layouts.app>
