<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email_reports' => ['nullable', 'boolean'],
            'security_alerts' => ['nullable', 'boolean'],
            'appearance' => ['nullable', 'in:system,light,dark'],
        ];
    }
}
