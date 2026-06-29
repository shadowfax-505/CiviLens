<x-layouts.app title="Upazila - CivicLens">
    <h1 class="text-3xl font-bold">{{ $upazila->exists ? 'Edit Upazila' : 'Create Upazila' }}</h1>
    <form method="POST" action="{{ $upazila->exists ? route('admin.geography.upazilas.update', $upazila) : route('admin.geography.upazilas.store') }}" class="mt-6 grid gap-4 md:grid-cols-2">
        @csrf
        @if ($upazila->exists)
            @method('PUT')
        @endif
        <select name="district_id" class="rounded border px-3 py-2 text-slate-950">
            @foreach ($districts as $district)
                <option value="{{ $district->id }}" @selected(old('district_id', $upazila->district_id) == $district->id)>{{ $district->name }}</option>
            @endforeach
        </select>
        <input name="name" value="{{ old('name', $upazila->name) }}" placeholder="Upazila name" class="rounded border px-3 py-2 text-slate-950">
        <input name="code" value="{{ old('code', $upazila->code) }}" placeholder="Code" class="rounded border px-3 py-2 text-slate-950">
        <button class="rounded bg-slate-950 px-4 py-2 text-white dark:bg-white dark:text-slate-950">Save upazila</button>
    </form>
</x-layouts.app>

