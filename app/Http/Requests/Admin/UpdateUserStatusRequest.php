<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administer', $this->route('user')) ?? false;
    }

    /**
     * @return array<string, string[]>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }
}
