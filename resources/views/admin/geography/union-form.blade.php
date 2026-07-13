<x-layouts.app title="Union - CivicLens">
    <h1 class="text-3xl font-bold">{{ $union->exists ? 'Edit Union / Municipality' : 'Create Union / Municipality' }}</h1>
    <form method="POST" action="{{ $union->exists ? route('admin.geography.unions.update', $union) : route('admin.geography.unions.store') }}" class="mt-6 grid gap-4 md:grid-cols-2">
        @csrf
        @if ($union->exists)
            @method('PUT')
        @endif
        <select name="upazila_id" class="rounded border px-3 py-2 text-slate-950">
            @foreach ($upazilas as $upazila)
                <option value="{{ $upazila->id }}" @selected(old('upazila_id', $union->upazila_id) == $upazila->id)>{{ $upazila->name }}</option>
            @endforeach
        </select>
        <select name="type" class="rounded border px-3 py-2 text-slate-950">
            <option value="union" @selected(old('type', $union->type) === 'union')>Union</option>
            <option value="municipality" @selected(old('type', $union->type) === 'municipality')>Municipality</option>
        </select>
        <input name="name" value="{{ old('name', $union->name) }}" placeholder="Name" class="rounded border px-3 py-2 text-slate-950">
        <input name="code" value="{{ old('code', $union->code) }}" placeholder="Code" class="rounded border px-3 py-2 text-slate-950">
        <x-leaflet-coordinate-picker :lat="$union->latitude" :lng="$union->longitude" label="Union / municipality map pin" />
        <button class="rounded bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950 md:col-span-2">Save union</button>
    </form>
</x-layouts.app>
