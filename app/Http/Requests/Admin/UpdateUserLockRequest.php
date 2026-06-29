<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserLockRequest extends FormRequest
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
            'locked' => ['required', 'boolean'],
        ];
    }
}
