<x-layouts.app :title="($organization->exists ? 'Edit Organization' : 'Create Organization').' - CivicLens'">
    <div>
        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Contractor Registry</p>
        <h1 class="text-3xl font-bold">{{ $organization->exists ? 'Edit Organization' : 'Create Organization' }}</h1>
    </div>

    <form method="POST" action="{{ $organization->exists ? route('admin.contractors.organizations.update', $organization) : route('admin.contractors.organizations.store') }}" class="mt-8 rounded-xl border bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @csrf
        @if ($organization->exists)
            @method('PUT')
        @endif

        <div class="grid gap-4 md:grid-cols-2">
            <label class="text-sm">Legal name
                <input name="legal_name" value="{{ old('legal_name', $organization->legal_name) }}" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                @error('legal_name') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
            <label class="text-sm">Trade name
                <input name="trade_name" value="{{ old('trade_name', $organization->trade_name) }}" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
            </label>
            <label class="text-sm">Registration number
                <input name="registration_number" value="{{ old('registration_number', $organization->registration_number) }}" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                @error('registration_number') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
            <label class="text-sm">Tax identification number
                <input name="tax_identification_number" value="{{ old('tax_identification_number', $organization->tax_identification_number) }}" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
            </label>
            <label class="text-sm">Company type
                <select name="organization_company_type_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                    @foreach ($companyTypes as $type)
                        <option value="{{ $type->id }}" @selected(old('organization_company_type_id', $organization->organization_company_type_id) == $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Industry
                <select name="organization_industry_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                    @foreach ($industries as $industry)
                        <option value="{{ $industry->id }}" @selected(old('organization_industry_id', $organization->organization_industry_id) == $industry->id)>{{ $industry->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Country
                <select name="country_id" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                    <option value="">Unassigned</option>
                    @foreach ($countries as $country)
                        <option value="{{ $country->id }}" @selected(old('country_id', $organization->country_id) == $country->id)>{{ $country->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Status
                <select name="status" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                    @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended', 'archived' => 'Archived'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $organization->status ?? 'active') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Website
                <input name="website" value="{{ old('website', $organization->website) }}" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
            </label>
            <label class="text-sm">Email
                <input name="email" type="email" autocomplete="email" value="{{ old('email', $organization->email) }}" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
                @error('email') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
            <label class="text-sm">Phone
                <input name="phone" value="{{ old('phone', $organization->phone) }}" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
            </label>
            <label class="text-sm">Established date
                <input name="established_date" type="date" value="{{ old('established_date', $organization->established_date?->format('Y-m-d')) }}" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">
            </label>
            <label class="text-sm md:col-span-2">Headquarters address
                <textarea name="headquarters_address" class="mt-1 w-full rounded border px-3 py-2 text-slate-950">{{ old('headquarters_address', $organization->headquarters_address) }}</textarea>
            </label>
        </div>

        <div class="mt-6">
            <x-leaflet-coordinate-picker
                :lat="$organization->headquarters_latitude"
                :lng="$organization->headquarters_longitude"
                lat-name="headquarters_latitude"
                lng-name="headquarters_longitude"
                label="Headquarters location"
            />
        </div>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="rounded bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-950">Save organization</button>
            <a href="{{ route('admin.contractors.organizations.index') }}" class="rounded border px-4 py-2 text-sm font-semibold dark:border-slate-700">Cancel</a>
        </div>
    </form>
</x-layouts.app>
