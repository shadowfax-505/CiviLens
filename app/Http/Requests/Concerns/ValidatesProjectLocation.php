<?php

namespace App\Http\Requests\Concerns;

use App\Models\AdministrativeUnion;
use App\Models\District;
use App\Models\Division;
use App\Models\Upazila;
use App\Models\Ward;
use Illuminate\Validation\Validator;

trait ValidatesProjectLocation
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $latitude = $this->input('latitude');
            $longitude = $this->input('longitude');

            if (($latitude === null) !== ($longitude === null)) {
                $validator->errors()->add('latitude', 'Latitude and longitude must be supplied together.');
                $validator->errors()->add('longitude', 'Latitude and longitude must be supplied together.');
            }

            $this->validateHierarchy($validator);
        });
    }

    private function validateHierarchy(Validator $validator): void
    {
        $division = $this->filled('division_id') ? Division::query()->find($this->integer('division_id')) : null;
        $district = $this->filled('district_id') ? District::query()->find($this->integer('district_id')) : null;
        $upazila = $this->filled('upazila_id') ? Upazila::query()->find($this->integer('upazila_id')) : null;
        $union = $this->filled('union_id') ? AdministrativeUnion::query()->find($this->integer('union_id')) : null;
        $ward = $this->filled('ward_id') ? Ward::query()->find($this->integer('ward_id')) : null;

        if ($division && $this->filled('country_id') && $division->country_id !== $this->integer('country_id')) {
            $validator->errors()->add('division_id', 'The selected division does not belong to the selected country.');
        }
        if ($district && $this->filled('division_id') && $district->division_id !== $this->integer('division_id')) {
            $validator->errors()->add('district_id', 'The selected district does not belong to the selected division.');
        }
        if ($upazila && $this->filled('district_id') && $upazila->district_id !== $this->integer('district_id')) {
            $validator->errors()->add('upazila_id', 'The selected upazila does not belong to the selected district.');
        }
        if ($union && $this->filled('upazila_id') && $union->upazila_id !== $this->integer('upazila_id')) {
            $validator->errors()->add('union_id', 'The selected union does not belong to the selected upazila.');
        }
        if ($ward && $this->filled('union_id') && $ward->union_id !== $this->integer('union_id')) {
            $validator->errors()->add('ward_id', 'The selected ward does not belong to the selected union.');
        }
    }
}
