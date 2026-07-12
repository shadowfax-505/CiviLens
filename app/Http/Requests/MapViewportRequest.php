<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Validator;

class MapViewportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'south' => ['nullable', 'numeric', 'between:-90,90', 'required_with:west,east,north'],
            'west' => ['nullable', 'numeric', 'between:-180,180', 'required_with:south,east,north'],
            'north' => ['nullable', 'numeric', 'between:-90,90', 'required_with:south,west,east'],
            'east' => ['nullable', 'numeric', 'between:-180,180', 'required_with:south,west,north'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('south')) {
                return;
            }

            if ((float) $this->input('north') <= (float) $this->input('south')) {
                $validator->errors()->add('north', 'The north boundary must be greater than the south boundary.');
            }

            if ((float) $this->input('east') <= (float) $this->input('west')) {
                $validator->errors()->add('east', 'The east boundary must be greater than the west boundary.');
            }

            if ((float) $this->input('north') - (float) $this->input('south') > (float) config('civiclens.maps.max_viewport_latitude_span')) {
                $validator->errors()->add('north', 'The requested map area is too large.');
            }

            if ((float) $this->input('east') - (float) $this->input('west') > (float) config('civiclens.maps.max_viewport_longitude_span')) {
                $validator->errors()->add('east', 'The requested map area is too large.');
            }
        });
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        throw new HttpResponseException(new JsonResponse([
            'message' => 'The map viewport is invalid.',
            'errors' => $validator->errors(),
        ], 422));
    }

    /** @return array<string, float> */
    public function viewport(): array
    {
        if (! $this->filled('south')) {
            return [];
        }

        return [
            'south' => (float) $this->input('south'),
            'west' => (float) $this->input('west'),
            'north' => (float) $this->input('north'),
            'east' => (float) $this->input('east'),
        ];
    }
}
