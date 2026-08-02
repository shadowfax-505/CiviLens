<?php

namespace App\Http\Requests\Admin\Sources;

use App\Models\SourcePublisher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSourcePublisherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SourcePublisher::class) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:120', 'unique:source_publishers,slug'],
            'source_class' => ['required', Rule::in(['government', 'non-government'])],
            'canonical_url' => ['required', 'url:https', 'max:2048'],
            'attribution_name' => ['required', 'string', 'max:255'],
            'rights_decision' => ['required', 'string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
