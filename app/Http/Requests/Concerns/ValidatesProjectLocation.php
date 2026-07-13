<?php

namespace App\Http\Requests\Concerns;

use App\Models\AdministrativeUnion;
use App\Models\District;
use App\Models\Division;
use App\Models\Project;
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
        $location = $this->effectiveLocation();
        $division = $location['division_id'] ? Division::query()->find($location['division_id']) : null;
        $district = $location['district_id'] ? District::query()->find($location['district_id']) : null;
        $upazila = $location['upazila_id'] ? Upazila::query()->find($location['upazila_id']) : null;
        $union = $location['union_id'] ? AdministrativeUnion::query()->find($location['union_id']) : null;
        $ward = $location['ward_id'] ? Ward::query()->find($location['ward_id']) : null;

        if ($division && $location['country_id'] && $division->country_id !== $location['country_id']) {
            $validator->errors()->add('division_id', 'The selected division does not belong to the selected country.');
        }
        if ($district && $location['division_id'] && $district->division_id !== $location['division_id']) {
            $validator->errors()->add('district_id', 'The selected district does not belong to the selected division.');
        }
        if ($upazila && $location['district_id'] && $upazila->district_id !== $location['district_id']) {
            $validator->errors()->add('upazila_id', 'The selected upazila does not belong to the selected district.');
        }
        if ($union && $location['upazila_id'] && $union->upazila_id !== $location['upazila_id']) {
            $validator->errors()->add('union_id', 'The selected union does not belong to the selected upazila.');
        }
        if ($ward && $location['union_id'] && $ward->union_id !== $location['union_id']) {
            $validator->errors()->add('ward_id', 'The selected ward does not belong to the selected union.');
        }
    }

    /** @return array<string, int|null> */
    private function effectiveLocation(): array
    {
        $project = $this->route('project');
        $fields = ['country_id', 'division_id', 'district_id', 'upazila_id', 'union_id', 'ward_id'];

        return array_reduce($fields, function (array $location, string $field) use ($project): array {
            $location[$field] = $this->has($field)
                ? $this->integer($field) ?: null
                : ($project instanceof Project ? $project->getAttribute($field) : null);

            return $location;
        }, []);
    }
}
