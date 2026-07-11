<x-layouts.app title="Ward - CivicLens">
    <h1 class="text-3xl font-bold">{{ $ward->exists ? 'Edit Ward' : 'Create Ward' }}</h1>
    <form method="POST" action="{{ $ward->exists ? route('admin.geography.wards.update', $ward) : route('admin.geography.wards.store') }}" class="mt-6 grid gap-4 md:grid-cols-2">
        @csrf
        @if ($ward->exists)
            @method('PUT')
        @endif
        <select name="union_id" class="rounded border px-3 py-2 text-slate-950">
            @foreach ($unions as $union)
                <option value="{{ $union->id }}" @selected(old('union_id', $ward->union_id) == $union->id)>{{ $union->name }}</option>
            @endforeach
        </select>
        <input name="name" value="{{ old('name', $ward->name) }}" placeholder="Ward name" class="rounded border px-3 py-2 text-slate-950">
        <input name="code" value="{{ old('code', $ward->code) }}" placeholder="Code" class="rounded border px-3 py-2 text-slate-950">
        <x-leaflet-coordinate-picker :lat="$ward->latitude" :lng="$ward->longitude" label="Ward map pin" />
        <button class="rounded bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950 md:col-span-2">Save ward</button>
    </form>
</x-layouts.app>
